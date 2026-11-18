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
