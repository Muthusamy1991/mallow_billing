# Code review: `UsageController@store` draft

The mid-level PR in the brief:

```php
public function store(Request $request)
{
    $customer = Customer::find($request->customer_id);
    $usage = new UsageEvent();
    $usage->customer_id = $customer->id;
    $usage->units = $request->units;
    $usage->usage_date = $request->usage_date;
    $usage->save();

    return response()->json(['status' => 'ok']);
}
```

This should not ship. Review comments I would leave on the PR, in the order I would ask the author to fix them.

## Blockers

1. **No validation.** `customer_id`, `units`, and `usage_date` are trusted blindly. Negative units, missing dates, and non-existent customers all reach the database (or fatal).
2. **`Customer::find()` on a missing id returns null, then `$customer->id` fatals.** That is a 500 for a client typo. Use `findOrFail` only after a Form Request has already scoped the id to the authenticated merchant.
3. **Not idempotent.** The brief is explicit: a retried request must not double-count. This insert has no `idempotency_key` and no unique constraint. At high throughput, retries *will* happen.
4. **No tenant isolation.** Any caller who can hit the route can attribute usage to any customer. Usage ingest must be keyed to the merchant (API key) and `exists:customers,id` must be constrained to `merchant_id`.
5. **No authorization / rate limiting.** This is the hottest write path in the product. Unbounded, unauthenticated writes are an availability bug.

## Should-fix before merge

6. **Read-then-write.** `find` plus `new` plus `save` is three round trips and a lost-update window. One insert, catch `UniqueConstraintViolationException` for the replay path.
7. **N+1 / extra query that is not needed.** We do not need the Customer model loaded if we already validated `customer_id` belongs to this merchant. Keep the write set to `merchant_id`, `customer_id`, `units`, `usage_date`, `idempotency_key`.
8. **Response is useless.** Callers cannot log an event id, cannot tell replay from insert, cannot debug. Return 201 vs 200 and the persisted row.
9. **`usage_date` type.** Unvalidated strings become whatever the driver casts. Require `date` and reject future dates unless we have a reason to allow backfill (we do allow backfill up to today).
10. **Mass-assignment / fillable discipline** is fine here because attributes are set explicitly — but once this grows, a Form Request + service is the right seam, not more controller code.
11. **This path will be called at high write volume.** Do not load relations, do not fire noisy model events, do not wrap in extra cache locks. Unique index + small transaction.

## What I asked the author to do instead

- Form request: `customer_id` exists for this merchant, `units` integer 1..1e6, `usage_date` date ≤ today, `idempotency_key` UUID (header or body).
- Middleware: merchant API key, `throttle:usage`.
- Service: insert, bump daily aggregate in the same transaction, on unique violation return the existing row and **do not increment**.
- Tests: replay does not double-count; foreign-tenant customer_id is 422; missing key is 401.

The replacement is `App\Http\Controllers\Api\UsageController` and `App\Services\UsageRecorder`. I would not nibble this draft into shape in the PR — the shape is wrong. Re-implement at the service boundary and keep the controller as HTTP.
