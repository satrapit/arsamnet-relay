# Delivery reconciliation — operator runbook

**State:** source authored on `feat/laravel13-foundation`; PHP/Pint/MySQL tests require independent execution. This is **not** approval for live SMS sending.

## Purpose and safety

`relay:reconcile-deliveries` is bounded and **never contacts a provider**. The default command is a read-only preview. An explicit `--apply` can:

- Move an unattempted, unscheduled/due `queued` delivery to `awaiting_provider`, provided no attempt or lease evidence exists; or mark an expired queued message `expired`.
- Move an **expired** `leased` delivery to `review_required` with `LEASE_EXPIRED_UNCERTAIN`. It will **not** automatically be retried.

It never changes `accepted`, `delivered`, `rejected` or existing `review_required` deliveries. It does not create a new job or send any SMS. A queued delivery with previous attempt evidence is skipped, even if its counters are inconsistent.

This tool is **operator-invoked only**: no scheduler entry was added. Introducing unattended auto-dispatch requires separate audit of crash windows, provider reconciliation and approvals.

## Safe inspection

```bash
php artisan relay:reconcile-deliveries --limit=100
```

After reviewing the database and using the intended environment:

```bash
php artisan relay:reconcile-deliveries --limit=100 --apply
```

Use a bounded limit (1–500). The command prints aggregate counts, never phone numbers, payloads, credentials or OTP. Run repeated small batches if required. For cPanel, avoid overlapping invocations and respect CLI timeouts. A dry run does not reserve the rows: counts are advisory under concurrency.

## Verification (developer Mac; no live provider requests)

```bash
php -l app/Console/Commands/ReconcileDeliveries.php
php -l tests/Feature/ReconcileDeliveriesTest.php
php vendor/bin/pint --test
php artisan test --filter=ReconcileDeliveriesTest
php artisan test
DB_DATABASE=arsamnet_relay_integration_test php scripts/mysql_publish_integration.php --expect=arsamnet_relay_integration_test
git diff --check
```

Never run destructive database commands or migration resets. The new unit/feature tests use SQLite and HTTP fakes; they do **not** establish MySQL concurrency or target-host behavior. Do not run this recovery command with `--apply` on production before reviewing test evidence and approval.

## Still missing

- Durable operator decision workflow and auditable resolution for `review_required` (no re-send simply because the operator clicked resolve).
- Independent MySQL tests of concurrent reconciliation and long-running provider timeouts.
- Safe provider status polling using Kavenegar's documented `sms/status` method and a verified status-code mapping; **not** implemented here.
- Cron deployment validation, alarms and an approved runbook for handling ambiguous sends.

Kavenegar's official PHP SDK exposes `status($messageid)` and `statusLocalMessageId($localid)`. These are distinct from the app's internal status endpoint; do not infer `delivered` solely from HTTP 200 or an accepted send reference.
