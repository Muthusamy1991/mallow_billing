# Narration script — 10 minute demo

Mallow asked for a **narrated** walkthrough in your own voice. The file `demo/Mallow-Billing-Demo.webm` is a captioned screen recording of the live app. Play it, and read this script over it (or record a new take in Windows Game Bar / OBS).

Pace: ~130 words/minute. Captions on screen match these beats.

## 0:00 Intro
This is Mallow Billing — a small multi-tenant backend that meters customer usage against a subscription plan and produces invoices. I will show the working app first, then the schema, queue, cache, and billing math behind it.

## 0:20 Merchants
Each merchant is a tenant. They own plans, customers, and an API key used to record usage. The seeded tenant is Northwind SaaS.

## 0:45 Dashboard
The brief asked the dashboard for three things: top five customers by usage this month, projected overage revenue for the current cycle, and customers whose usage dropped more than fifty percent month over month.

KPIs on the left are month-to-date units, pace-based overage, churn-risk count, and active subscriptions.

## 1:30 Top 5
Those rankings come from `usage_daily_aggregates`, not from the raw event table. That is the scale decision: at fifty lakh usage rows, dashboards and invoices must never scan `usage_events`.

## 2:15 Churn risk
Churn risk is a same-length comparison: this month-to-date versus the same number of days last month, so a mid-month view is not punished for being incomplete. Glacier Legal and Harbor Finance are the seeded drop-offs.

## 3:00 Recording usage
The form on the dashboard is the same writer as `POST /api/usage`. I record two hundred and fifty units for Aurora Labs. The API requires `X-Api-Key` and an idempotency UUID. A retried request returns two hundred and does not increment the rollup.

On ingest I also bump the daily aggregate in the same transaction so the dashboard is not a day behind. The queued job rebuilds that day from `SUM(usage_events)` — that job is the source of truth.

## 4:15 Customer and segments
Billing does not trust `subscriptions.plan_id` as history. It walks `subscription_segments`. When a plan is assigned, we snapshot base price, included units, overage rate, and name. If someone later edits the plan, old invoices stay correct.

## 5:15 Mid-cycle upgrade
Requirement eight: usage before the change is billed at the original plan, usage after at the new plan, with proration on both windows. Frost Logistics was upgraded Growth to Scale. The old segment closes the day before the effective date.

I do not allow a monthly-to-yearly switch mid-subscription. That mixes cycle lengths. Cancel and resubscribe. Documented in the README.

## 6:15 Invoices
The scheduler runs aggregation at 00:15 and invoices at 00:45. On the first of the month it closes the previous calendar month. Invoices are unique on customer plus cycle, so re-running the job updates lines instead of duplicating.

Each invoice has a base line and an overage line per segment. Base is `price * segment_days / cycle_days`. Included units are prorated then floored. Overage is `max(0, usage - included) * rate`. All money is bcmath.

## 7:30 Architecture
Write path: API key, throttle one hundred twenty per minute, insert with unique idempotency key, bump rollup, later chunked rebuild.

At five million rows I would keep this shape. Next operational step is monthly partitions on `usage_date`. That forces uniqueness to `(idempotency_key, usage_date)`, which is fine because clients retry the same payload.

Plan cache key `plans.{id}`, one hour, forgotten by `PlanObserver` on save and delete. Dashboard cache is sixty seconds only — busting it on every ingest would stampede.

## 8:45 Tests and rollout
`php artisan test` — eighteen tests: proration, exact allowance, one-unit overage, mid-cycle upgrade, idempotent replay, foreign-tenant 422, rate limit, aggregation rebuild, dashboard.

Rollout: ingest first, aggregate job second, invoices last in draft. If usage recording fails silently, look at `POST /api/usage` success versus five-hundreds, and insert rate versus retry rate. A spike in unique-constraint hits is retries. A drop in inserts with five-hundreds is the outage.

## 9:30 Close
That is the hand-off: segments are the price source of truth, never query events from the dashboard, and the corners I cut are listed in the README. Thanks.
