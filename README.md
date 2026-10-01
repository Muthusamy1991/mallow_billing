# Mallow Billing — Subscription Billing & Usage-Metering

Laravel 12 backend that meters customer usage against a merchant plan and produces prorated invoices, including mid-cycle upgrades/downgrades.

Built against the Mallow ATL mini-task (superset of the Senior Laravel brief).

## Setup

Requires PHP 8.2+, Composer, and MySQL/MariaDB (XAMPP is fine).

```bash
cp .env.example .env
php artisan key:generate
```

`.env` defaults for this XAMPP box:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mallow_billing
DB_USERNAME=root
DB_PASSWORD=
```

```bash
php artisan migrate:fresh --seed
php artisan test
php artisan serve --host=127.0.0.1 --port=8088
```

Set `APP_URL` to match how you open the app so CSS and links resolve:

- XAMPP: `http://localhost/mallow-billing/public` (or `http://localhost/mallow-billing` if using the root `.htaccess`)
- `artisan serve`: `http://127.0.0.1:8088`

Demo merchant API key: `demo-northwind-key`.

Queue worker (database driver):

```bash
php artisan queue:work
```

Scheduler (aggregation at 00:15, invoices at 00:45):

```bash
php artisan schedule:work
```

## Architecture

```
POST /api/usage  →  UsageRecorder (idempotent insert + hot daily rollup)
                         │
                         ▼
              usage_events  (append-only, unique idempotency_key)
                         │
         AggregateDailyUsageJob (chunked SUM → upsert)
                         ▼
           usage_daily_aggregates  (dashboard + billing reads)
                         │
         GenerateCycleInvoicesJob (chunked by subscription)
                         ▼
              invoices + invoice_line_items
```

Controllers stay thin. Billing math lives in `BillingCalculator`. Persistence/orchestration lives in `InvoiceService`, `UsageRecorder`, `DailyUsageAggregator`, `SubscriptionService`, `DashboardService`. Plan reads go through `PlanCache`.

### Schema (normalized + indexed)

| Table | Role |
|---|---|
| `merchants` | Tenant. Unique `api_key`. |
| `plans` | Price book. Unique `(merchant_id, name)`. |
| `customers` | Unique `(merchant_id, email)`. |
| `subscriptions` | One commercial relationship. `billing_cycle` is immutable. |
| `subscription_segments` | Plan snapshots. Open-ended `ended_at` null = current. |
| `usage_events` | Raw writes. Unique `idempotency_key`. Indexes `(customer_id, usage_date)`, `(merchant_id, usage_date)`. |
| `usage_daily_aggregates` | Denormalized daily SUM. Unique `(customer_id, usage_date)`. |
| `invoices` | Unique `(customer_id, cycle_start, cycle_end)`. |
| `invoice_line_items` | Base + overage per segment. |

`merchant_id` is denormalized onto usage and segments so tenant-scoped scans do not join through customers.

### How this holds up at 50L+ usage rows

50 lakh (5M+) event rows is comfortable for InnoDB **if dashboards and invoices never scan it**.

What I would keep in production:

1. **Hot path writes only `usage_events`.** Unique `idempotency_key` makes retries cheap (`INSERT` + catch duplicate). No `SELECT` before write.
2. **All reads go to `usage_daily_aggregates`.** That table grows with `customers × days`, not with event volume. At 10k customers this is a few million rows/year, still small.
3. **Chunked aggregation** pages by `customer_id` (keyset, not `OFFSET`) so a 5M-row day still processes in bounded memory.
4. **Monthly RANGE partitioning on `usage_events(usage_date)`** once a table crosses ~20–50M rows. Caveat: a unique key must include the partition column, so uniqueness becomes `(idempotency_key, usage_date)` — clients already retry the same payload, so that is acceptable. Until then, **monthly archive tables** (`usage_events_2026_10`) achieve the same isolation without the unique-key constraint change.
5. **Drop FKs on `usage_events` only** if ingest CPU shows up in `ibdata`/FK checks. Integrity stays in the app (customer must belong to merchant).
6. **Batch ingest** in front of MySQL (Redis stream / SQS → 500-row inserts) if a single tenant exceeds ~2–3k events/sec. The public API stays the same.
7. **Redis** for plan cache and idempotency-key hot lookup if the unique index becomes the write bottleneck (unlikely at 5M).

I would **not** denormalize running monthly totals onto `customers`. The daily rollup already answers dashboard and billing queries with a tight date-range index.

## API

### `POST /api/usage`

Headers: `X-Api-Key`, optional `Idempotency-Key` (or body).

```json
{
  "customer_id": 1,
  "units": 25,
  "usage_date": "2026-10-01",
  "idempotency_key": "11111111-1111-1111-1111-111111111111"
}
```

- 201 on first insert, 200 on replay.
- Rate-limited: 120 req/min per API key (`throttle:usage`).
- A replay never increments the daily aggregate a second time.

### `GET /api/merchants/{id}/dashboard`

Returns:

- top 5 customers by usage this calendar month
- projected overage revenue for the current cycle (pace-based)
- customers whose usage dropped **>50%** vs the same day-range last month

Cached 60s per merchant/month.

### `POST /api/customers/{id}/plan-change`

```json
{ "plan_id": 3, "effective_date": "2026-10-12" }
```

Closes the current segment the day before, opens a new segment with a **price snapshot**.

## Billing rules

Assumptions (ambiguous in the brief — documented rather than blocked):

1. **Cycles are calendar-aligned.** Monthly = 1st through last day of the month. Yearly = Jan 1 through Dec 31. A subscription that starts on Jan 10 is billed Jan 10–31 at `days/days_in_month` of base price and included units.
2. **Included units are prorated per segment, then floored.** Money uses `bcmath` at 4 decimal places.
3. **Mid-cycle plan change** splits the cycle. Usage on days `[start, end]` of a segment is billed only against that segment's snapped `included_units` and `overage_rate`. Base price is prorated by segment days / cycle days.
4. **Billing cycle cannot change mid-subscription.** Cancel and resubscribe. Mixing monthly and yearly windows in one invoice is undefined.
5. **Plan price edits do not rewrite history.** Segments copy `base_price`, `included_units`, `overage_rate`, `plan_name` at creation time.
6. **Projection** for the dashboard is not an invoice: current-cycle usage is scaled by `days_in_cycle / elapsed_days`, then run through the same calculator. This over-estimates if usage is front-loaded; that is acceptable for a risk/revenue pulse.
7. **Churn risk** compares this month-to-date against the same number of days last month, not a full vs partial month.
8. **Ingest updates the daily rollup immediately** so the dashboard is not a day behind. The queued job **rebuilds** the rollup from `SUM(usage_events)` and is the reconciliation path. Invoices always read aggregates, never raw events.

```text
invoice_total = Σ segment (
    base_price * segment_days / cycle_days
  + max(0, usage_segment − floor(included * segment_days / cycle_days)) * overage_rate
)
```

## Caching

| Key | TTL | Invalidation |
|---|---|---|
| `plans.{id}` | 3600s | `PlanObserver` on `saved` / `deleted` |
| `plans.merchant.{id}` | 3600s | same |
| `dashboard.merchant.{id}.{month}` | 60s | TTL only (usage is high-write; explicit bust would stampede) |

Production store: Redis. This XAMPP box has no `redis` PHP ext, so local uses the `database` cache store (same keys, same observer). Tests use `array`.

## Queueing

- `AggregateDailyUsageJob` — daily, chunk size 500 customers, keyset pagination.
- `GenerateCycleInvoicesJob` — runs every day; on the 1st it invoices the previous month (and on Jan 1, the previous year).
- Idempotent invoices: unique `(customer_id, cycle_start, cycle_end)`. Re-running updates line items, does not duplicate.

Artisan:

```bash
php artisan billing:aggregate 2026-09-30 --sync
php artisan billing:invoices --month=2026-09 --sync
```

## Tests

```bash
php artisan test
```

Coverage:

- proration, exact-allowance, 1-unit overage
- mid-cycle upgrade/downgrade (old rate before the cut, new rate after)
- usage idempotency and tenant isolation
- rate limit on `/api/usage`
- chunked aggregation rebuild
- dashboard top-5 / overage / churn-risk
- plan cache invalidation

## Code review exercise

The draft `store()` from the brief was **not merged**. Review notes: [`docs/CODE_REVIEW.md`](docs/CODE_REVIEW.md). The shipped endpoint is `UsageController` + `UsageRecorder`.

## Rollout & monitoring

Ship behind the merchant API key, with the unique `idempotency_key` index in place **before** any client retries are enabled. Roll out ingest first (events + rollup), aggregation job second, invoice generation last — invoices can be backfilled. Feature-flag invoice persistence (`status=draft` until totals look right for a shadow cycle).

If usage recording starts silently failing, the first thing on-call should look at is **`POST /api/usage` success rate vs 4xx/5xx, plus the `usage_events` insert rate vs the caller’s retry rate**. A drop in inserts with a rise in unique-constraint hits is retries (healthy). A drop in inserts with a rise in 5xx or timeout is the outage. Dashboard lag without an ingest drop means the aggregate job is stuck, not the API.

## Handing this off

1. **Segments are the source of truth for price, not `subscriptions.plan_id`.** Billing always walks `subscription_segments`. If you add discounts or credits, attach them to a segment or a cycle, not to the live plan row.
2. **Do not query `usage_events` from the dashboard.** If you need a new metric, extend the daily rollup (or a monthly rollup) rather than scanning events.
3. **Corners I cut under the time-box:** no Sanctum/user login (merchant API key only), database queue instead of Redis/Horizon, no real-money rounding policy beyond bcmath 4dp / display 2dp, no webhook/outbox for “invoice issued”, yearly plans are implemented but the seeder is monthly-only, no partition migration shipped (documented only).

## Prompt log

AI-assisted session notes: [`prompts/PROMPT_LOG.md`](prompts/PROMPT_LOG.md).
