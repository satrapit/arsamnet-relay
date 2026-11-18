# Arsam Relay — Current Verification Status

**As of:** 2026-11-17  
**Branch:** `feat/laravel13-foundation`  
**PR:** [#1](https://github.com/satrapit/arsamnet-relay/pull/1), **Draft**.  
**Environment:** developer Mac; full Laravel tests use in-memory SQLite. Dedicated real MySQL 8 tests use `arsamnet_relay_integration_test`.  
**Safety:** production publish is OFF by default. No live provider adapter or outbound transport is connected.

> This is the authoritative *latest verified result* summary. Older sections in
> individual documentation files are historical progress snapshots; a statement
> saying "not run" may refer to work that was later tested.

## Confirmed PASS (developer-provided Mac output)

| Check | Verified result | Scope |
| --- | --- | --- |
| PHP syntax | PASS | FakeDispatchCoordinator, its tests, and MySQL integration script |
| Focused fake dispatch | **15 tests, 76 assertions PASS** | priority, no fallback, timing, tenant, invalid encrypted data, fake accepted/result lifecycle |
| Full Laravel PHPUnit | **116 tests, 430 assertions PASS** | SQLite in memory, not full-scale concurrency |
| Laravel Pint | **91 PHP files PASS** | no formatting changes |
| Independent real MySQL 8 integration | **PASS** | transaction rollback, idempotency/replay, two-process same-key race, route selection, fenced leases, one attempt, signed fake receipt, scheduled-first-route fake end-to-end |
| Isolated MySQL fixture cleanup | **PASS** | runner returned application test data to empty; migration ledger remains |
| Primary schema verification | **PASS** | `arsamnet_relay`: 28 tables including migrations, 192 documented fields, 61 declared indexes, 32 restricted FKs, 4 migration records; read-only |
| Source control | **PASS** | `git diff --check` and `git status --short` produced no output on Mac after last pull |

**Important limitations:** These outputs demonstrate that the scripted scenarios
passed. They do not establish network delivery, production cPanel compatibility,
complete tenant-penetration testing, high-concurrency load coverage, failure
recovery from a mid-send crash, SMTP/SMS provider contracts or genuine delivery
receipts. The two-process race is one case, not a stress benchmark.

## Implementation boundary

The local/testing-only `FakeDispatchCoordinator::dispatchFirstEligible`
uses the read-only `ProviderRouteSelector`, then the no-network
`FakeOutboundProviderAdapter`; it never falls back after an attempt
or unknown result. A durable `started` attempt precedes simulated
provider acceptance. Accepted != delivered; only a separately verified,
fake-only HMAC receipt moves a delivery to delivered.

No public provider callback, real SMS/SMTP/webhook transport, production
send worker, automatic failover, retry or dead-letter consumer is connected.
The fake code is explicitly blocked outside local/testing.

## Safe next steps

1. Review [Provider Go-Live Gates](PROVIDER_GO_LIVE_GATES.md) and choose
   one concrete provider/channel and actual API contract before building
   any network adapter.
2. Implement provider-specific credential validation, transport security,
   error classification and callback/status proof, with test doubles first.
3. Require explicit security review, isolated MySQL tests and an
   operator-approved, strictly scoped sandbox rehearsal before enabling
   real outbound sends.
4. Preserve the **Draft PR**, `main`, environment flags, and separate
   development/production data until that review is complete.

### Repeatable regression commands (developer Mac)

```bash
cd ~/Sites/arsamnet-relay
php vendor/bin/pint --test
php artisan test

DB_DATABASE=arsamnet_relay_integration_test \
  php scripts/mysql_publish_integration.php \
  --expect=arsamnet_relay_integration_test

php artisan relay:db-verify --expect=arsamnet_relay
git diff --check
git status --short
```

**Do not** recreate, drop, wipe or migrate the already existing integration
schema to repeat the script. Never run tests/migrations on `arsamnet_relay`.


## New Kavenegar SMS source — pending independent verification (2026-11-17)

The preceding table is the last developer-confirmed PASS baseline (116 PHP tests/430 assertions; fake-only MySQL). Since that run, a gated Kavenegar SMS integration was authored: privileged encrypted account settings, fixed-host REST single/sendarray/VerifyLookup, machine endpoints and redacted status, durable attempts, OTP cooldown, per-client quota and 24 new HTTP-faked PHP tests. The current full suite is expected to have **140 tests if green**, but **NO NEW TESTS HAVE RUN on developer Mac**. The standalone isolated MySQL runner was extended to exercise all three Kavenegar operations with Http::fake (no real network) and is likewise NOT RUN. See [KAVENEGAR.md](KAVENEGAR.md). Sending gates remain OFF. No real Kavenegar delivery or production readiness claim is authorized.


## Kavenegar milestone — source authored, Mac tests pending (2026-11-18)

The latest **confirmed** result remains the 116 PHP tests/430 assertions
and fake-only MySQL integration above. New code implements Kavenegar
single SMS, 1–25 item SendArray and VerifyLookup OTP; tenant-scoped
machine API, encrypted provider account settings, priority route selection,
bounded synchronous dispatch and sanitized durable Attempt/status history.
`Http::fake` tests cover provider behavior without contacting Kavenegar.

Newly added tenant self-service URLs are opt-in, gated by client owner/admin
membership, MFA, recent password and disabled-by-default
`client-provider-overrides` flag. Client portal navigation/onboarding is
not built. Overall **28 new Kavenegar tests are authored but not yet run**,
so the full suite is expected to contain **144 tests** if green.
The separate guarded MySQL integration runner has offline Kavenegar
single/bulk/OTP cases, also NOT RUN after the changes. No real network
send or third-party callback/status verification has occurred.


## Developer-reported Kavenegar QA and subsequent recovery source (2026-11-18)

- **Developer-reported PASS (not independently rerun here):** 28 focused Kavenegar tests / 151 assertions; full PHPUnit 144 tests / 581 assertions; Pint 100 PHP files; git diff check. This supersedes the earlier 116/140/144 *pending* milestones above as a user-provided result. Exact pushed formatting diff and final dedicated MySQL/Kavenegar integration result were not established by this report and remain to be checked.
- **New commit(s), NOT RUN:** `relay:reconcile-deliveries` provider-free reconciliation command, six PHP feature test scenarios and `docs/DELIVERY_RECOVERY.md`. This bounded command previews by default, stages only never-attempted due `queued` deliveries, expires obsolete queued records and quarantines expired leases as `review_required`. It never sends or retries. See runbook.
- **Pending:** lint/Pint, full PHPUnit, isolated real MySQL recovery/concurrency evaluation and code review for these new commits. Do not add the six cases to the 144-confirmed total until actually run.
- **Official API discovery:** Kavenegar `sms/status` / `sms/statuslocalmessageid` exist, but status polling and verified status mapping are still NOT IMPLEMENTED.
- **Safety:** global publish and Kavenegar live send flags default OFF; no production change, no real SMS or `main` merge. PR #1 remains Draft.


## Recovery feature Mac regression (developer output, 2026-11-18)

- **PASS (developer Mac):** `php -l` on reconciliation command and new feature test.
- **PASS (developer Mac):** `ReconcileDeliveriesTest` — 6 tests / 21 assertions, with dry-run, idempotent staging, expiry, expired-lease quarantine, prior-attempt refusal and invalid limit.
- **PASS (developer Mac):** full Laravel PHPUnit — **150 tests / 602 assertions** (SQLite in-memory). No test failures.
- **Pint issue:** 102 PHP files scanned; exactly one style issue in `tests/Feature/ReconcileDeliveriesTest.php` (`fully_qualified_strict_types` / `ordered_imports`). A follow-up feature-branch commit replaces the fully qualified class reference with an ordered `use App\\Services\\DeliveryLeaseManager;` import. **Pint after that change has NOT been run on Mac**.
- `git diff --check` and `git status --short` produced no error/output in the developer log before this follow-up commit.
- **Still pending:** independent real MySQL 8 reconciliation tests, concurrent repair/dispatch race evaluation, cPanel Cron rehearsal, provider delivery status proof and any real Kavenegar send. Go-live flags stay OFF; PR #1 remains Draft.


## Recovery formatting gate closed — developer Mac (2026-11-18)

- **PASS:** `php vendor/bin/pint --test` — **102 PHP files**, zero style issues after the ordered-import fix.
- **PASS:** `php artisan test --filter=ReconcileDeliveriesTest` — **6 tests / 21 assertions**, no failures.
- **Previously PASS:** full `php artisan test` — **150 tests / 602 assertions** before the import-only correction. The full suite was not rerun after that correction.
- **Source control:** developer `git pull --ff-only` advanced the feature branch to `da15513`; subsequent `git diff --check` and `git status --short` produced no reported output.
- **Next gate:** separately execute the guarded, pre-existing `scripts/mysql_publish_integration.php` on the exact pre-migrated, empty `arsamnet_relay_integration_test` schema. The current MySQL runner covers the prior delivery lifecycle, but does **not** yet exercise the new reconciliation command or concurrent recovery. Do not interpret a MySQL pass as validating reconciliation until explicit recovery assertions are implemented and run.
- **Production gate:** live Kavenegar sending remains disabled; no provider status proof or manual operator resolution workflow is implemented. No merge to `main`.


## MySQL Kavenegar regression PASS; dedicated recovery MySQL checks authored (2026-11-18)

Developer Mac output confirms the **existing** isolated MySQL 8 script PASS for rollback, sequential/concurrent idempotency (two PHP processes), staging, fenced leases, tenant route selection, durable attempts, signed fake receipt, ambiguous quarantine, and Kavenegar single/SendArray/VerifyLookup under `Http::fake`. Isolated test fixtures were cleaned to empty. Read-only primary schema verifier PASS: 28 tables including migrations, 192 documented columns, 61 indexes, 32 restricted foreign keys, four migration records. Git worktree was clean.

**New source, NOT YET RUN on Mac:** strengthened `ReconcileDeliveries` conditional update to recheck message schedule/expiry and correlated prior-attempt absence at write time. Expanded the same guarded real-MySQL runner with preview, queued staging/repeat apply, expired queued rows, old attempt evidence refusal, expired-lease quarantine and no-network assertions. No schema migrations, production sends, or Cron changes. **These tests are not yet evidence of concurrent reconciliation under separate processes**; an explicit race test remains a separate gate.

Run `php artisan test --filter=ReconcileDeliveriesTest`, full `php artisan test`, `php vendor/bin/pint --test`, and the guarded integration runner again; no result for these new commits is yet verified.

 
## Recovery MySQL validation and first two-process race (2026-11-18)

- **Developer Mac PASS:** PHP syntax on reconciliation command and integration runner; Pint 102/102 files; focused recovery 6 tests/21 assertions; full Laravel suite 150 tests/602 assertions.
- **Developer Mac PASS:** guarded isolated MySQL 8 integration now includes recovery dry run, repeat staging, queued expiry, refusal with previous attempt evidence and expired-lease quarantine. Existing rollback, two-process publisher idempotency, provider routing and offline Kavenegar single/bulk/OTP also passed. Integration fixture cleanup completed.
- **NEW SOURCE ONLY, NOT YET TESTED:** extra two-process barrier test in `scripts/mysql_publish_integration.php` races an inert `StageDeliveryJob` with `relay:reconcile-deliveries --apply` using separate PHP/MySQL connections and validates exactly one safely staged delivery with no new provider attempts. This is **not** evidence of in-flight HTTP crash safety, concurrent operator resolution, or high-load stress.
- **Required next checks:** `php -l scripts/mysql_publish_integration.php`, `php vendor/bin/pint --test`, full PHPUnit and guarded MySQL runner on the already migrated/empty `arsamnet_relay_integration_test` schema. No migration/reset or real Kavenegar traffic.
- `main` remains untouched; PR #1 is Draft; publish/send flags default OFF.


## Two-process recovery QA and final formatter gate — developer Mac (2026-11-18)

- Developer reports that all tests other than the previously flagged Pint formatting completed successfully after pulling `f4a8de8`. The full detailed concurrent test output was not pasted, so retain this as a **developer-reported** pass, not an independently inspected per-scenario run.
- After formatter-only commit `736e3a4`, the developer ran `php vendor/bin/pint --test`: **PASS across 102 PHP files**. `git diff --check` and `git status --short` returned no output, confirming a clean local worktree at that point.
- Previously supplied full PHPUnit output: **150 passed, 602 assertions**. Previously supplied independent MySQL output confirmed safe offline recovery and Kavenegar mock operations.
- No recent actual Kavenegar delivery or target-host Cron rehearsal. In-flight HTTP crash recovery and audited operator resolution remain open; the two-process stage-vs-reconcile race is not a substitute.
- Next architectural checkpoint: inspect the current `SecurityAudit` allowlist and `review_required` transition rules before adding an operator resolution API, any automatic recovery, or status polling. Keep live sends disabled and PR #1 Draft.


## Operator review triage (source only; Mac QA pending, 2026-11-18)

- **Prior developer evidence:** Pint 102 PHP files PASS; `git diff --check` and clean worktree after formatting. Full PHPUnit 150/602 and recovery MySQL integration were reported successful before this milestone.
- **New source, NOT RUN on Mac:** privileged per-client review queue, GET `/clients/{client}/delivery-reviews` and password-confirmed POST `.../{delivery}/acknowledge`, new `DeliveryReviewController`, Blade view, link from Clients, `SecurityAudit` allowlist limited to `delivery_id` and static `review_action`, and seven PHP Feature tests.
- Signed audit event `delivery.review_acknowledged` is recorded transactionally for an exact tenant-owned `review_required` delivery. No state transition, provider status claim, retry, send, migration, queue, or Cron change. Audit unavailability fails the action closed.
- **Next gates:** PHP lint for authored controller/tests, focused `DeliveryReviewTest`, full PHPUnit, Pint, and isolated MySQL regression. Acknowledgement is triage, **not** provider-confirmed resolution. Live sends OFF; PR Draft; no `main` merge.


## Manual Kavenegar status inquiry milestone — code authored, Mac QA pending (2026-11-18)

- Verified official Kavenegar PHP SDK exposes `sms/status` by numeric `messageid`. A separate `ARSAM_KAVENEGAR_STATUS_ENABLED=false` gate was added; it does **not** require enabling SMS sends. No real status request was executed.
- Added `KavenegarSmsAdapter::queryStatus` with fixed-host HTTPS POST, strict single-record message-ID validation and conservative normalized output (`provider_reports_delivered` only for code 10; other valid codes `not_confirmed`; malformed/timeouts `unknown`).
- Added tenant-bound `KavenegarDeliveryStatus` for accepted attempts with `kv-<numeric id>` reference, and a staff-only, recent-password-confirmed, six-per-minute POST action in the Delivery Reviews page. The action records signed `delivery.provider_status_queried` metadata without exposing raw provider response, OTP, phone or key.
- **No delivery state transition, receipt insertion, retry, resend or automatic polling**, even on provider code 10. Current audit logs are the only persisted inquiry history.
- Added four focused adapter tests plus four DeliveryReview feature tests; expanded the isolated real MySQL test runner with an HTTP-faked status lookup. These **new tests are NOT RUN** and the changes are NOT verified on Mac.
- Before considering status network access, run PHP lint, Pint, focused/full PHPUnit, and the guarded real-MySQL integration script. Keep the status/send gates OFF pending separate approval. PR #1 Draft, `main` unchanged.


## Super Admin operational settings migration and PHPUnit fatal fix (2026-11-18)

- **Developer Mac input:** isolated MySQL integration including two-process recovery and status-only Kavenegar lookup PASS, with fixture cleanup. PHP lint PASS for new files. Pint found one test formatting issue; PHPUnit failed to start because `KavenegarStatusAdapterTest::status()` collided with PHPUnit's final `TestCase::status()`. These failures are not provider regressions.
- **New commits, NOT TESTED on Mac:** renamed the test helper to `providerStatusResponse()`, corrected `DeliveryReviewTest` class method spacing, and migrated all runtime operational switches from `.env` to the existing audited Super Admin Pennant Feature Flags UI.
- Runtime flags `message-publishing`, `kavenegar-sending`, `provider-status-queries` default OFF independently. The numeric `kavenegar-hourly-limit` defaults to 100, adjustable between 1 and 10000 by Super Admin via password-confirmed route with signed Audit. No migration; existing Pennant feature store is reused. Account-specific provider credentials remain encrypted in the existing per-client account settings UI.
- `.env.example` now retains infrastructure/secret configuration only, including APP_KEY, DB, SMTP, ARSAM_AUDIT_HMAC_KEY and initial admin provisioning. Former `ARSAM_PUBLISH_ENABLED`, `ARSAM_KAVENEGAR_SEND_ENABLED`, `ARSAM_KAVENEGAR_STATUS_ENABLED` and `ARSAM_KAVENEGAR_HOURLY_LIMIT` are no longer read by runtime.
- Existing PHP test setup and isolated MySQL runner were switched to explicit Pennant gates, with the runner using the **array** store to avoid modifying global flags in the fixture-only MySQL schema. New role/limit tests were added.
- **QA pending:** full PHPUnit, Pint, isolated MySQL runner, syntax/lint and review of all gate transitions. No live provider contact, Cron change, database migration, or `main` merge; PR #1 Draft.
