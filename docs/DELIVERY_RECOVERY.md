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


## Manual triage (new source, unverified on developer Mac)

The platform staff dashboard now links **Clients → Delivery reviews** for each client. Only verified and MFA-confirmed Super Admin/Platform Admin users with `clients.manage` can open the paginated list of tenant-scoped `review_required` delivery IDs. The list contains no recipient addresses, message bodies, OTPs or credentials.

The operator can select **Acknowledge review**, protected by recent password confirmation and CSRF. This creates a signed `delivery.review_acknowledged` security event inside a transaction with an exact client/delivery match and row lock. Allowed audit fields are `delivery_id` and the static `review_action=investigating`. No arbitrary human notes or provider errors are accepted. If auditing fails, acknowledgement fails closed.

**Acknowledgement is not resolution.** The original delivery remains `review_required`; no provider calls, state transitions, retry, failover or delivered claim take place. It records only that a staff member started investigating. A validated provider status query and a separately approved evidence-based resolution workflow are still needed.

Routes:
- `GET /clients/{client}/delivery-reviews`
- `POST /clients/{client}/delivery-reviews/{delivery}/acknowledge`

New `DeliveryReviewTest` PHP Feature tests cover safe listing, signed audit, missing privileges, foreign-client/non-review deliveries, password confirmation, bad identifiers and audit failure. **Not yet run on the developer Mac.** No database migration or Cron change is required.


## Per-delivery audit history — source authored, Mac verification pending (2026-11-19)

The existing signed `security_events` records now have a dedicated **history view per delivery** under Clients → Delivery reviews. Click a Delivery ID or open `GET /clients/{client}/delivery-reviews/{delivery}/history`. The route inherits authenticated, verified and platform-MFA middleware and the `clients.manage` authorization check.

This is a **logical history view on the existing signed audit table, not a new physical table**. It intentionally avoids a duplicate schema and migration. Events are limited to `delivery.review_acknowledged` and `delivery.provider_status_queried`, filtered by the exact tenant ID and the exact `context_json.delivery_id` value; pagination is limited to 25 records.

Each event's HMAC is verified on read. Valid records show timestamp, event classification, sanitized action, numeric provider status and actor ID. Failed verification displays **Integrity failed** and hides the unverified action, provider code and actor ID. No recipient, OTP, SMS body, raw provider response, API secret, sending action, replay, or Delivery state update is performed.

Three new Feature tests cover an exact tenant/delivery scope, sanitized verified history, a tampered event, guests/support/foreign clients, and absence of HTTP calls. **These tests have not been executed on the developer Mac.** Prior 2026-11-19 Mac report confirmed all previous tests PASS, but that report does not cover this new history milestone.

This is not yet a provider-evidence settlement workflow: manual or automatic `delivered` transitions remain excluded until status-code mapping, provenance and concurrency conditions have separately passed review. Existing SMS send and status gates remain OFF by default.
