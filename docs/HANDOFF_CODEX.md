# Codex handoff — 2026-11-17

> **Latest verified QA and go-live limits:** see [STATUS.md](STATUS.md) and [PROVIDER_GO_LIVE_GATES.md](PROVIDER_GO_LIVE_GATES.md). Later historical sections may say pending for work subsequently verified.


Repository: https://github.com/satrapit/arsamnet-relay (PUBLIC). Branch: feat/laravel13-foundation; main remains initialized only. Product Arsam Relay. No deployment or release tag.

## Current status

- Original specification is docs/MASTER_PROMPT.md. The current ERD has 27 tables, 192 columns and 32 references. All four initial migrations ran successfully on dedicated local MySQL 8.0.33; formal schema signoff remains pending.
- Laravel 13 source, Fortify/Pennant configuration, admin MFA gate, protected CLI Super Admin, clients/key issuance/revocation, optional CIDR-bound machine API, global flags, responsive views and tests are committed.
- Composer/npm dependency installation, Vite build and 35 Laravel tests (108 assertions, SQLite in-memory) succeeded on the developer Mac. Official dbdiagram import, full TailAdmin integration, Graphify install, live provider adapters and cPanel deployment remain untested/unimplemented.
- Composer.lock and package-lock.json are absent until real dependency resolution. Do NOT manufacture them manually.

## Next work — ordered

1. Reconcile the DBML with all migrations, Fortify/Pennant real stubs and native MySQL 8. Review composite keys and nullable shared-template uniqueness. Obtain owner approval and then use disposable MySQL database for migrate/fresh/rollback tests.
2. Resolve Composer and npm packages locally; run composer validate, composer install, npm install, npm run build and commit generated locks. Verify local Font Awesome assets in Vite manifest.
3. Run php artisan about, route:list, schedule:list, php artisan test, vendor/bin/pint --test, composer audit. Fix all failures and document actual output, not guesses.
4. Security review: Fortify login/MFA/confirmation/recovery, seedless first admin, key lifecycle, IP allowlist IPv4/IPv6, same tenant scoping, request rate limits, CSRF/CSP and session cookies. Add tests where missing.
5. Integrate actual selected TailAdmin Free components with Laravel 13 and CSP; mobile-first layout, responsive navigation, no runtime CDN.
6. Implement queue/domain message coordinator and fake-first provider contracts. Avoid retry of ambiguous SMS timeouts. Apply backend Pennant flags and tenant policies. Then real provider adapters, callbacks and webhooks.
7. Implement OpenAPI 3.1 + verified Postman and HTTPie imports for implemented endpoints; complete docs and host operations.
8. On installed Graphify CLI, run graphify --version and graphify codex install --project; use $graphify with freshness checks and ignore graphify-out.

## Safe commands

    composer validate
    composer install
    npm install
    npm run build
    php artisan about
    php artisan route:list
    php artisan schedule:list
    php artisan test
    ./vendor/bin/pint --test
    composer audit

Never run migrate:fresh on production or a developer database with data. Only on disposable DB after approval. Exact cPanel PHP binary, Cron and network/SSL are unverified.

## Blocking limitations

This authoring environment lacks Composer, required PHP extensions and network access to package registries. Actual PHP lint, Laravel/DB tests and frontend build were NOT RUN. This is a reviewable implementation draft, not a proven functioning app.

## Security review continuation (2026-11-17)

- Imported one TailAdmin Free card component with its upstream MIT notice. Improved mobile drawer and accessible menu state. Full TailAdmin integration is still unfinished.
- Added redacted auth failure/MFA/throttle signals, operator security monitor with 15-minute counts and Cron heartbeat freshness, HMAC-based event integrity checks, and recent-password protection for recovery codes.
- Run `php scripts/offline/AuditIntegritySmoke.php` for an isolated no-Composer integrity smoke check (verified in the authoring container). **It does not test real Laravel or MySQL**.
- Run `php artisan relay:verify-audit --limit=1000` only after dependencies, migrations and ARSAM_AUDIT_HMAC_KEY are configured; this only detects edits to inspected rows, not deleted records.
- Full PHP lint and PHPUnit tests remain NOT RUN; do not merge before running them on the actual development environment.

## Key rotation continuation

Read the updated ClientApiKeyController and tests/Feature/ApiKeyLifecycleTest.php. Confirm that the database transaction and lockForUpdate prevent concurrent rotation and prevent key ID crossing tenant boundaries. Verify expiry rejection and immediate revocation using MySQL 8, and confirm token display never leaks to logs/cache. Tests are authored, NOT executed in Laravel yet.

## CIDR and tenant admin continuation (2026-11-17)

- Added `app/Support/IpCidr.php` to normalize/match IPv4/IPv6 CIDRs; invalid masks and /0 are rejected. Machine bearer authentication now uses this shared parser.
- Added Platform Admin/Super Admin pages to add/remove per-client IP allowlist rules (max 64, tenant-scoped, recent password confirmation, HMAC-redacted audit); removing the last active rule requires exact `UNRESTRICT` input because an empty enabled list means any IP may authenticate with a valid key.
- Review and run `tests/Feature/ClientIpAllowlistTest.php` and `tests/Feature/MachineApiTest.php` on a disposable MySQL 8 database. Test no-MFA, unauthorized role, concurrent network insert/delete, canceled last-rule deletion, cross-client IDOR, trusted proxy configuration, and revocation after changing rules.
- Run `php scripts/offline/IpCidrSmoke.php` without Composer: 23 normalization/matching assertions passed in the authoring environment. `php -l` passed for IpCidr, its smoke script, ClientIpAllowlistController, and ClientIpAllowlistTest.
- Run `php scripts/check_migration_parity.php` to detect table/column name drift; its parser passed good/bad **synthetic fixture** tests. An independent read-only GitHub source comparison showed 27/27 table names and 192/192 column names aligned across DBML and current migrations; official DBML parsing, types, defaults, indexes and MySQL execution remain NOT RUN.
- Corrected a broken Carbon namespace in the client API key expiry Blade expression.

## Developer Mac installation feedback and immediate next actions (2026-11-17)

See docs/LOCAL_DEVELOPMENT.md. Composer (133 packages) and npm/Vite install/build (102 packages) PASSED, Laravel 13.42 bootstrap and 42 route registrations PASSED. `schedule:list` failed due to missing default SQLite DB + cache_locks, not a verified scheduler defect. The developer must create private .env from .env.example (local/MySQL, no secrets committed), run `CACHE_STORE=array php artisan schedule:list` first, then run safe in-memory PHPUnit tests and report errors. `composer.lock` and `package-lock.json` generated on Mac are not in the remote repo yet. PSR4 warning resolved by moving offline scripts to scripts/offline/. PHPUnit DB settings now forced in phpunit.xml to lower the risk of accidentally using an externally configured database, but cached config must be cleared. The real MySQL 8 migration rehearsal is still outstanding.


## Mac PHPUnit output / database identity (2026-11-17)

- Unit suite 2 PASS/8 assertions; Feature 7 PASS, 1 FAIL in stock ExampleTest, 18 not reached due to stop-on-failure. Tests for API key lifecycle (3) and IP allowlists (4) already passed in SQLite :memory: using supplied phpunit.xml.
- Fixed ExampleTest to assert the intentional root redirect to /dashboard and guest redirect to /login; run complete Feature suite without stop-on-failure to discover remaining failures.
- `composer audit --locked` reports no known advisories (current installed lock). Composer validation, offline smoke tests and database-free schedule listing pass on Mac.
- MySQL `db:show` and `migrate:status` fail 1049 "Unknown database arsamnet_relay", host 127.0.0.1, port 3306. User says a database exists. Inspect real host/port/schema and private .env; do not create or migrate until reconciled, and do not ask user for DB_PASSWORD.
- Vite deleted tracked public/build/.gitignore. Removed from Git; generated /public/build/ is already ignored by root .gitignore.
- Local Composer and npm lockfiles have not yet been committed. They need review and commit on dev machine; do not forge a lockfile from a log.


## Full Mac Feature suite and migration safety gate (2026-11-17)

- Mac Feature suite PASS: 26 tests/82 assertions. Unit suite PASS: 2 tests/8 assertions. Combined 28 tests/90 assertions, executed with in-memory SQLite; not validation of MySQL FKs.
- MySQL 8.0.33 connection confirmed as `arsamnet_relay` on localhost:3306. Old 1049 name mismatch resolved (hyphen to underscore); `migrate:status` says migration tracking table not yet present.
- Warning: `db:show` reported 393 tables in all visible schemas because current local MySQL user is root; **do not mistake these for Arsam Relay tables**. Read-only preflight now queries `information_schema.tables WHERE table_schema = DATABASE()` only.
- Added `app/Console/Commands/MigrationTargetPreflight.php`, `php artisan relay:db-preflight --expect=arsamnet_relay` (read-only and refuse non-local/nonempty/mismatched databases), removed scaffold's predictable example seeded user, added `tests/Feature/DatabaseBootstrapSafetyTest.php` (3 tests unexecuted), fixed composer setup to preserve existing APP_KEY.
- Only after `php artisan config:clear`, full test rerun and preflight PASS on an explicitly disposable local empty schema should the initial `php artisan migrate --database=mysql` rehearsal be attempted. If migration fails, STOP; MySQL DDL may have partially committed. Do not use `migrate:fresh` against shared data.
- Local `composer.lock`/`package-lock.json` exist but still await review and push. Keep PR draft.

 
## Milestone 1 first real MySQL migration completed — 2026-11-17

- The user ran `php artisan test` with **31 PASS / 96 assertions** on SQLite :memory:. All initial MySQL 8.0.33 migrations ran without error on fresh, dedicated local `arsamnet_relay`. Preflight confirmed exactly zero tables before migrating; `migrate:status` reports the four project migrations in batch 1.
- Added `app/Support/SchemaManifest.php` and read-only `app/Console/Commands/VerifyDatabaseSchema.php`: `php artisan relay:db-verify --expect=arsamnet_relay`. Checks 27 declared tables + migrations ledger, 192 documented column definitions, declared indexes and 30 restricted FK relationships (including tenant composites). No writes or drops; test results pending.
- Added 4 new PHPUnit checks: tests/Unit/SchemaManifestTest.php (2) and tests/Feature/DatabaseSchemaVerificationSafetyTest.php (2). Must run on Mac.
- **Do not run migrate:fresh, refresh, rollback, db:wipe, or any DROP on this migrated project database.** If verifier fails, identify metadata mismatch and review schema before change.
- Lockfiles `composer.lock` and `package-lock.json` are still local user files, not yet committed. On Mac, validate/commit/push them explicitly and only from the project branch, without touching .env.

 
## Schema/FK/Pint output — latest follow-up (2026-11-17)

- Full Mac Laravel suite PASS: 35 tests, 108 assertions (SQLite :memory:).
- `relay:db-verify` on MySQL initially FAIL with ONLY 2 unexpected direct client FKs, in `subscriptions` and `deliveries`. They already exist in migrations and actual MySQL. Added them to docs/ERD.dbml, which now declares 32 FKs; **no DDL needed**.
- Added `scripts/check_migration_relationships.php` to detect DBML/migration FK drift. Static check found **32 matches / no differences** in current GitHub sources; run it on Mac and re-run `relay:db-verify`.
- `./vendor/bin/pint --test` found 20 style issues among 61 files. On Mac run `./vendor/bin/pint`, then `./vendor/bin/pint --test`, then `php artisan test`. Commit formatting once tests pass; do not replace domain logic while doing a style-only cleanup.
- Still uncommitted local lockfiles: composer.lock and package-lock.json. Add to feature branch after review, then push; keep PR #1 draft.

## Current CSV dictionary blocker fixed in source (2026-11-17)

Developer Mac confirmed 35 tests/110 assertions, MySQL schema verifier full PASS (28 tables / 192 documented fields / 61 indexes / 32 FKs / 4 migration records), Pint --test PASS after 20 files reformatted, Composer strict validation PASS. `scripts/validate_design.php` failed because docs/ERD.csv had a literal two-line spreadsheet extraction preamble above its header. Source updated to remove just that preamble and improve strict dictionary/header checks; **await Mac re-run** before claiming PASS. Pint local edits and generated composer.lock/package-lock.json are uncommitted; commit reviewed changes on Mac once this passes.

 
## PHP-only QA implementation (2026-11-17)

The user explicitly requests PHP for all tests whenever practical. Replaced and removed the 3 Python schema test scripts, adding:

- `app/Support/OfflineDesignChecks.php` (read-only, dependency-free), with methods to validate ERD/CSV samples, compare 27 migration tables/192 columns, and compare 32 FKs to DBML.
- `scripts/validate_design.php`, `scripts/check_migration_parity.php`, `scripts/check_migration_relationships.php` as standalone PHP commands.
- `tests/Unit/OfflineDesignChecksTest.php` containing five PHPUnit cases (await Mac execution).

The validator was linted with PHP CLI and passed on an isolated representative PHP fixture (27 tables, 192 fields, 32 FKs, 27 sample CSVs), including negative mutation checks. This does **not** establish passing tests on the developer's real repository until the new PHP commands are run on Mac. Do not restore the retired .py files for QA. Node/npm remain necessary only for frontend asset builds. Existing 35 Laravel tests/110 assertions, real MySQL verifier and Pint were PASS before this change; local Pint/lockfile edits await review and user commit.

 
## PHP migration-FK checker fix — current Mac feedback (2026-11-17)

The first developer Mac run of PHP-only QA passed design CSV checks (27/192/32/27) and column parity (27/192) but failed on `Unsupported FK columns: 'delivery_id'`. One PHPUnit case failed; total **39 PASS / 1 FAIL (114 assertions)**. This was a parser limitation, not invalid MySQL migration structure. Real MySQL metadata inspection separately passed 28 tables, 192 fields, 61 indexes, 32 restricted FKs, four migrations. Added support for both scalar and array syntax to `OfflineDesignChecks::phpColumns()` and regression tests for scalar, composite and malformed expressions. Offline PHP fixture tests passed in authoring environment, Mac rerun pending. Pint reported 1 new class styling issue among 66 files; apply Pint on Mac after updating the source.

**WORKTREE MERGE SAFETY:** Developer Mac still holds uncommitted Pint formatting changes to 20+ PHP files, including `app/Support/OfflineDesignChecks.php`, and untracked composer.lock/package-lock.json. Pulling may fail because the new branch commit touches that same class. Back up/stash that file's formatting changes before fast-forward, then reapply Pint. Do not force-reset the whole working tree or lose generated lockfiles.


## Direct publish staging on feature branch (2026-11-17)

Following 43 PASS / 118 assertions on developer Mac, a small, opt-in portion of Milestone 3 was added to `feat/laravel13-foundation`:

- `POST /api/v1/messages` uses the pre-existing machine key/IP/rate limiter, strict `messages.publish` scope and 16–128 character `Idempotency-Key`; requires 1–25 active tenant-owned targets; payload JSON object with 16 KiB request ceiling.
- `MessagePublisher` persists encrypted message and target deliveries in a transaction with native database queue inserts, using the same DB connection. Tenant+idempotency unique index handles duplicates. HMAC request fingerprint distinguishes replay from conflicting reuse.
- `StageDeliveryJob` contains a delivery ULID only; **no provider calls**. It can move `queued` to `awaiting_provider` or `expired`, without creating a delivery attempt. The feature is OFF in default .env.
- `tests/Feature/PublishMessagesTest.php` has 9 new feature test cases (NOT EXECUTED YET). Run on developer Mac; do not merge if failures.
- No new migration, no SQL changes, no prod send. Quotas, concurrent MySQL idempotency stress, topic fanout, provider adapters, lease claims, retry/ACK/DLQ, monitoring all remain future scope.
- User's local Pint changes and actual lockfiles remain to be committed; this repo branch has not yet received them. Avoid overwriting those files. Keep PR #1 draft.


## Latest Publish staging validation (2026-11-17)

- Mac reported 9 Publish Feature tests PASS (48 assertions), total Laravel tests **52 PASS (166 assertions)** on SQLite :memory:. `php -l` for new PHP files PASS. Separate read-only real MySQL schema inspection PASS (28 tables, 192 documented fields, 61 indexes, 32 restricted FKs, four migrations).
- Pint found three style-only issues on 72 PHP files: `app/Services/MessagePublisher.php`, `app/Support/InvalidPublishTargets.php`, and `app/Support/MessagePublicationConflict.php`. GitHub source now contains docblock alignment/empty-body formatting fix, but full Mac Pint rerun remains pending. Local `php vendor/bin/pint` may still be required for whitespace/unary-operator fixes. No application behavior change or migrations.
- Existing local developer Pint changes and generated lockfiles remain uncommitted; push those in separate commits after final suite passes. Keep draft, feature OFF by default.

 
## Final developer Pint/tests result before lockfile commit — 2026-11-17

The developer Mac now reports **52/52 PHPUnit tests (166 assertions) PASS**, `pint --test` **72 PHP files PASS**, `relay:db-verify` **28 tables including migrations / 192 fields / 61 indexes / 32 restricted FKs / 4 migrations PASS**, `composer validate --strict` PASS and `git diff --check` with no errors.

The remaining source-control task is local: review and commit **25 modified tracked PHP files from Pint** (137 insertions/75 deletions), then review and commit the actual locally generated `composer.lock` and `package-lock.json` as a separate build commit, and `git push origin feat/laravel13-foundation`. These exact local files are inaccessible to the GitHub connector until pushed; never fabricate them. Check clean status and stash inventory after push. Do not merge PR yet.

Next technical QA gate: concurrent idempotent publish and transaction rollback on a **separate, explicitly disposable** MySQL integration database, with PHP test harness. Do not run destructive refresh/rollback/migration tests against `arsamnet_relay`. Provider sending stays disabled.

 
## Locks pushed; new MySQL integration test scripts (2026-11-17)

Developer Mac completed the two local Git commits and push:
- `bf5e94f` Laravel Pint formatting across 25 files.
- `c007f22` genuine `composer.lock` and `package-lock.json`.
- Composer validate/audit and npm high-severity audit all passed. Mac
  `git status --short` was clean.
- The baseline remains 52 PHPUnit PASS / 166 assertions and read-only
  MySQL metadata verification PASS.

Added `scripts/mysql_publish_prepare.php` (creates only a brand-new exact
`arsamnet_relay_integration_test` schema) and
`scripts/mysql_publish_integration.php` (guards local MySQL schema and empty
tables; checks transactional rollback, sequential replay/conflict, staging-only
state change, two-process idempotency race; cleans only its own fixtures).
The code is in the feature branch, but MySQL execution on Mac is **NOT RUN**.
Setup and execution details: `docs/MYSQL_INTEGRATION.md`.

Important: the normal PHPUnit harness is **SQLite-only by force in
phpunit.xml**, so run this integration CLI separately. Never use
`migrate:fresh` or `db:wipe` on the current development schema. New
integration schema creation requires real user action and is not assumed done.
PR remains Draft; sending remains disabled by default.

 
## MySQL integration passed; new delivery lease primitive (2026-11-17)

Developer Mac **PASS**: newly created isolated schema `arsamnet_relay_integration_test` passed preflight (0 tables/views), ran the original four migrations, then passed independent PHP MySQL integration: nested rollback of domain+queue writes; sequential same-key replay; conflict rejection; StageDeliveryJob without provider call; two concurrent processes yielding exactly one message/delivery; cleanup back to empty. Existing main `arsamnet_relay` was not changed in this process. Not equivalent to load testing.

Follow-up source is now committed but **NOT YET TESTED ON MAC**: `app/Services/DeliveryLeaseManager.php`, ten tests in `tests/Feature/DeliveryLeaseManagerTest.php`, and new lease cases in `scripts/mysql_publish_integration.php`. This internal lease manager supports client/target/status checks, random UUID fencing tokens, bounded TTL and expiry-capped renewals, and quarantines stale leases into `review_required` with `LEASE_EXPIRED_UNCERTAIN`. No public lease route, provider call, ACK/NACK, retry or requeue has been enabled. Lease expiry must **not** automatically resend uncertain OTP/SMS. Run PHP lint, focused/full PHPUnit, Pint and the expanded isolated MySQL integration script. No need to migrate again or create another schema. PR stays Draft.


## DeliveryLeaseManager PHP regressions green; MySQL lease expansion pending (2026-11-17)

Developer Mac reports: PHP lint for all 3 lease-related files PASS; 10 focused `DeliveryLeaseManagerTest` PASS / 41 assertions; complete PHPUnit 62 PASS / 207 assertions; Pint 76/76 PHP files PASS. Pint formatted the new lease test file (one local diff not yet committed). No provider sends or DDL changes. The expanded lease section in `scripts/mysql_publish_integration.php` has **not** been executed in the supplied terminal output. Next execute it only against `arsamnet_relay_integration_test` (already migrated and cleaned), inspect source differences, commit/push style fix, and retain Draft PR. Do not recreate/migrate the schema.

 
## Real MySQL Lease QA COMPLETE (2026-11-17)

Developer executed the expanded isolated PHP MySQL script, and every line passed: rollback of message/delivery/native job, sequential replay/conflict, provider-free stage, fenced claim and renewal, expiry quarantine, two-process same-key race and fixture cleanup to empty. Pint **76/76 PASS** and local lease test formatting-only commit `ea6b919` pushed. Git worktree clean. Last full PHPUnit: 62/62 tests and 207 assertions PASS. **No actual provider sends**. Existing `arsamnet_relay_integration_test` remains migrated and empty; do not recreate/migrate it again. PR Draft; Publish disabled by default.


## Durable attempt bookkeeping after confirmed lease QA (2026-11-17)

All previously reported PHP and MySQL lease tests PASS; user pushed
`ea6b919` one-file Pint formatting correction and local worktree clean.

The next committed source addition (MAC TESTS **NOT YET RUN**) provides
`App\Services\DeliveryAttemptManager` and
`App\Enums\ProviderAttemptOutcome`. These are **INTERNAL ONLY**, never
call provider APIs, decrypt provider credentials, or enqueue send jobs.
`begin` validates tenant, live lease, enabled channel-matched
provider account and explicit route, then inserts a `started` attempt
and sets attempt_count=1 atomically; subsequent begins are refused.
`finish` requires the active fencing token and outstanding first
attempt, atomically normalizing accepted/rejected/retryable/ambiguous.
Accepted is not delivered; the latter two go to manual review, with
no automated retry. Provider reference and HTTP code are strict/sanitized.

Ten new PHP Feature cases are in
`tests/Feature/DeliveryAttemptManagerTest.php`. The existing MySQL
integration CLI gained fake provider-account/route + accepted/ambiguous
result assertions and FK-safe cleanup. No migrations, no public route,
no sender, no callback and no provider I/O. See `docs/PROVIDERS.md`,
`docs/MYSQL_INTEGRATION.md`, and `docs/TESTING.md`.
Keep feature OFF, PR Draft, and run Mac PHP tests/Pint/MySQL integration
before any next implementation.


## Actual provider-attempt milestone QA PASS — 2026-11-17

Mac reports **10/10 DeliveryAttemptManager feature tests (50 assertions) PASS**, full **72/72 Laravel PHPUnit (257 assertions) PASS**, Pint **79 files PASS** and standalone isolated MySQL integration PASS with durable `started` attempts, accepted without delivered semantics, no duplicate finish, ambiguous quarantined without retransmission, two-process same-key race and fixture cleanup. Read-only main MySQL schema verify remains PASS (28 tables/192 fields/61 indexes/32 FKs/4 migrations). A **single local Pint-only edit** to `scripts/mysql_publish_integration.php` remains uncommitted in the output; review/commit/push it from Mac. No DDL or external sends. Keep PR Draft and publish off. Next security boundary is verified receipt/callback authentication with anti-replay, ownership and correlation; do not mark delivered from an unverified provider claim.


## Fake-only receipt contract added — NOT RUN on Mac (2026-11-17)

Developer pushed `4a6f8fd` as a style-only import order change.
Existing confirmed QA remains 72 Laravel tests/257 assertions, 79
Pint files and expanded isolated MySQL attempt results PASS.

New internal-only `FakeSignedCallbackReceipt` verifies a fake provider
account's encrypted `callback_secret` (>=32 bytes), strict
HMAC-SHA256 over account ID + ten-digit Unix timestamp + exact raw JSON
body (300-second skew limit), one opaque provider ref correlated to
exactly one accepted attempt, enabled authorized provider route,
tenant ownership and active client. Exact signed event IDs are HMACed
in `callback_receipts` under the existing unique
(provider_account_id,event_id_hmac) index. It atomically inserts the
receipt and changes `accepted -> delivered` only in `local/testing`.
No public route or external adapter exists; never treat a real provider's
arbitrary status claim as delivery proof without provider-specific
signature/capability review.

Nine new `FakeSignedCallbackReceiptTest` PHP Feature tests plus fake
signed-receipt checks in the existing guarded MySQL integration CLI.
MySQL cleanup was updated to delete callback_receipts before their FK
delivery_attempts. **None of the newly added checks have run on developer
Mac yet**. Run lint, focused/full PHPUnit, Pint, and isolated CLI; do
not re-create or migrate the existing test schema. Keep Publish OFF
and PR Draft.


## Fake-only signed receipt QA PASSED (Mac, 2026-11-17)

Developer reports 81 Laravel PHP tests/292 assertions all PASS. Isolated MySQL integration PASS including authenticated fake receipt accepted->delivered, duplicate/replayed and tampered receipt denied, plus prior rollback/idempotency/lease/attempt checks; test fixture cleanup succeeded. Primary schema verifier PASS (28 tables, 192 fields, 61 indexes, 32 restrict FKs, 4 migrations), and Git status clean. No public callback route, no external network or real adapter. PR Draft, Publish OFF. Next phase can integrate *internal fake-only* coordinator for a full local lifecycle test; never connect production worker to it.


## FakeDispatchCoordinator (new unverified source) — 2026-11-17

Previous Mac baseline: 81/81 PHP tests with 292 assertions PASS,
independent MySQL fake HMAC receipt/replay and transaction/lease/race
PASS, main schema verifier PASS, Git worktree clean.

New `FakeDispatchCoordinator` provides manual **local/testing-only**
fake dispatch, never a real adapter or queue worker. It checks a
tenant-owned, staged delivery, enabled `fake-only` provider and
channel-specific route, encrypted JSON fake callback secret, then
claims a fenced lease, persists one started attempt and simulates
accepted with a random fake provider reference; it never sends or
marks delivered. A separately authenticated fake HMAC receipt is
still necessary. Seven new `FakeDispatchCoordinatorTest` PHP Feature
cases and an isolated MySQL end-to-end integration case are authored
but NOT RUN on developer Mac. No DDL or migration modifications.
Next run focused/full PHP tests, Pint, isolated real MySQL CLI, and
inspect `git status --short` before any further implementation.
No public callback or provider transport; keep PR Draft/publish OFF.


## End-to-end fake dispatch confirmed PASS (2026-11-17)

Mac reports **88/88 PHP tests with 331 assertions PASS**, focused FakeDispatchCoordinator **7/7 with 39 assertions PASS**, 83/83 Pint PHP files PASS and all 3 new/modified files `php -l` PASS. Guarded isolated real MySQL 8 integration PASS for Publish → Stage → Lease → Attempt → accepted → separately verified fake HMAC receipt → delivered, plus original rollback/idempotency/race checks and cleanup back to empty. Read-only primary schema verification PASS (28 tables/192 fields/61 indexes/32 FKs/4 migrations). Local Git status clean. This is a fake-only internal rehearsal; no actual SMS, SMTP, Webhook, callback route or production sender enabled. PR remains Draft.


## Provider adapter contract foundation (new, NOT RUN on Mac)

Confirmed prior Mac baseline: 88 full Laravel PHP tests/331 assertions
PASS, focused FakeDispatchCoordinator seven tests/39 assertions PASS,
Pint all 83 PHP files PASS and isolated real MySQL full fake-only
message lifecycle PASS. Main schema metadata verifier PASS, Git worktree
clean.

Added `App\Contracts\Providers\OutboundProviderAdapter`,
`App\Enums\ProviderCapability`, `App\DTO\OutboundProviderRequest`,
`App\DTO\OutboundProviderResult` and local/test-only
`App\Providers\Adapters\FakeOutboundProviderAdapter`. The contract
separates provider transport from publisher, tenant routing, durable
attempts and verified callback receipt. The request DTO keeps sensitive
destination/body private, redacts debug, and blocks serialize/unserialize;
response DTO only exposes sanitized outcome/status/ref. Fake adapter
handles typed SMS/email/webhook channels with **NO I/O**, returning
synthetic accepted-not-delivered; it cannot run in production.
The interface is not yet wired into the existing fake coordinator or any
queue/sender. This is intentional to preserve the proven fake-only
pipeline while the adapter contract is verified.

Nine PHP unit contract tests were added to
`tests/Unit/ProviderAdapterContractTest.php`. NEW tests/code **NOT YET
RUN** on Mac; next lint, run focused/full Laravel tests, Pint, and repeat
the already migrated isolated MySQL integration regression. No migrations,
provider contacts, route changes, secrets, public callback endpoint or
workflow files. Publish OFF, PR remains Draft.


## Contract QA PASS, fake coordinator now uses contract (pending tests)

Developer Mac confirmed nine provider adapter contract tests (37 assertions), all **97 Laravel tests/368 assertions**, Pint **89 PHP files**, and entire isolated real MySQL fake-only integration PASS, with primary schema verifier and Git clean.

The follow-up source (not yet Mac-tested) refactors `FakeDispatchCoordinator` so its actual fake accepted result comes from `FakeOutboundProviderAdapter::send(OutboundProviderRequest)`. Only inside local/testing: validates authorized fake provider/channel/route and decrypted callback secret, retrieves tenant-matched encrypted target/message data, decrypts only to an in-memory nonserializable DTO, rejects corrupt/oversized values before leasing, durably begins one attempt, then invokes the adapter, normalizes result via `DeliveryAttemptManager`. No network or production dispatch. If fake adapter throws or returns accepted without a reference, the started attempt is quarantined as ambiguous; no automatic release/retry. Three new negative tests reject bad encrypted target/message and oversized plaintext; **10 focused fake coordinator tests** now, **100 full PHP tests expected** if green. No DDL, public route, API, worker or callback changes. Run PHP lint, Pint, focused/full PHPUnit and same guarded isolated MySQL regression before moving further. PR Draft.


## Provider adapter integrated into fake coordinator — Mac QA PASS (2026-11-17)

Local developer confirmed PHP lint, Pint **89/89 files PASS**, focused
FakeDispatchCoordinator **10 tests/51 assertions PASS**, full
Laravel **100 tests/380 assertions PASS** and real MySQL standalone
integration on the isolated schema fully PASS including the now
adapter-wired fake-only end-to-end path. Main development schema verifier
PASS (28 tables/192 fields/61 indexes/32 FKs/4 migrations). Git
working tree clean. This test scope has no real SMS/SMTP/webhook calls,
live network or public callback routes. PR remains Draft; Publish OFF.


## Verified fake adapter integration; read-only route selection added (2026-11-17)

Mac confirmed full 100 PHPUnit tests/380 assertions PASS; targeted FakeDispatchCoordinator 10/51 PASS; Pint 89 files PASS; real MySQL 8 guarded integration PASS including adapter-wired fake-only end-to-end dispatch; read-only original DB verifier PASS; Git status clean.

Next source in feature branch (**NOT YET TESTED ON MAC**): `app/Services/ProviderRouteSelector.php` is a read-only query that returns one eligible provider account ID for an active client's unleased, unattempted `awaiting_provider` delivery, matching enabled route/account/channel/tenant and message expiration, sorted by numeric route priority. It never decrypts credentials, begins an attempt, sends, fails over after uncertain results, updates state, or changes schema. Nine new tests in `tests/Feature/ProviderRouteSelectorTest.php` and additional guarded real MySQL checks in `scripts/mysql_publish_integration.php`. Previous full suite 100; expected **109 tests**. Run lint, focused/full Laravel PHP tests, Pint and isolated MySQL runner on existing migrated empty `arsamnet_relay_integration_test`. Keep PR Draft, Publish OFF; do not start real delivery.


## Single initial provider selector — test gate pending

The previous Mac run (full PHP 100/380, Pint 89, real MySQL fake-only
lifecycle) passed. Added a read-only first-attempt provider selection
service on the feature branch, choosing the lowest numeric priority
enabled route whose account belongs to the client or is shared. A
simultaneous development update introduced `ProviderRouteSelector`
and a second `ProviderRouteResolver`; those were **consolidated** into
the single `ProviderRouteSelector` to avoid parallel business logic.
The selector now also blocks future-scheduled messages and excludes
fake-only provider accounts outside local/testing. The one remaining
`ProviderRouteSelectorTest` has **11 PHP Feature tests**, plus a
first-attempt selection checkpoint in the existing isolated MySQL runner.
All routing additions/changes are **NOT YET RUN ON MAC**.

Important: this selector is READ-ONLY and never authorizes retransmission
or automatically fails over an attempted, leased or uncertain delivery.
The existing fake coordinator still accepts an explicit fake account ID;
no sender/cron/provider route was enabled. PHP lint, focused/full PHP tests,
Pint and guarded isolated real MySQL regression remain the next QA gates.
No migration or reset is required; PR Draft, Publish OFF.


## ProviderRouteSelector QA PASS — developer Mac (2026-11-17)

The consolidated first-attempt read-only provider selector passed **11 focused tests / 25 assertions**; complete Laravel suite passed **111 tests / 405 assertions**, and Pint passed **91 PHP files**. The already-migrated isolated MySQL 8 integration reran successfully including tenant-scoped staged-only route selection, existing rollback/lease/attempt/idempotency/two-process race and full fake-only authenticated receipt, with fixtures cleaned to empty. Read-only primary MySQL schema verifier passed (28 tables, 192 fields, 61 indexes, 32 FKs, 4 migrations). Local Git clean. Earlier estimate of nine routing tests/109 total was superseded by two negative cases (scheduled future and fake-only production). No real sends or migrations; PR Draft.


## Initial route wired into fake-only coordinator (new source, NOT RUN on Mac)

Mac confirmed prior 111 PHPUnit tests / 405 assertions PASS; 11 focused ProviderRouteSelector /25 assertions PASS; 91-file Pint PASS; existing guarded MySQL 8 transaction, race, route, fake adapter and signed callback checks PASS, with isolated fixtures cleaned and original schema read-only verifier PASS.

New `FakeDispatchCoordinator::dispatchFirstEligible(clientId,deliveryId)` is a **local/testing-only manual method** that asks `ProviderRouteSelector` for exactly one initial eligible provider account, then invokes existing `dispatch(clientId,deliveryId,accountId)`. It does not iterate providers or fail over after failed/ambiguous results. If the top eligible account uses a non-fake adapter, the simulated dispatch fails closed, without trying another. Direct explicit fake dispatch is also newly guarded against future `scheduled_at` values before claim. Five new cases in `FakeDispatchCoordinatorTest` (now 15, expected total 116 Laravel tests), and the already-guarded isolated PHP/MySQL runner now asserts future-scheduled fake dispatch refusal plus first-route acceptance after the scheduled time. **No Mac QA for these latest code changes yet.** Run lint, Pint, focused/full Laravel tests and the existing isolated MySQL runner; never recreate/migrate/reset the test schema. No production worker, real adapter send, public callback route or schema changes. PR Draft; Publish OFF.


## Kavenegar initial SMS integration — NOT YET TESTED on Mac (2026-11-17)

Last verified baseline: 116 Laravel tests/430 assertions, Pint 91 files, real MySQL fake-only pipeline and original schema verifier PASS. Added client-specific encrypted Kavenegar API key/sender config via protected Clients → SMS provider, HTTP-fake-only tests of POST /api/v1/sms/send, /bulk, /otp and GET /api/v1/sms/messages/{id}. New adapter uses official api.kavenegar.com HTTPS POST form, SendArray and VerifyLookup, 3/8s timeouts, TLS verified, redirects and retry disabled. Bounded request path first durably publishes+stages, then leases and writes one attempt per delivery before any HTTP call; bulk maps unique IDs; provider errors are normalized. Global ARSAM_PUBLISH_ENABLED and ARSAM_KAVENEGAR_SEND_ENABLED remain false, no real provider request sent. Client SMS quota default 100 recipients/hour and 1 OTP per recipient/minute; idempotent replays never resend. 24 PHP new tests and HTTP-faked MySQL checks are authored but NOT RUN. Full suite expected 140 if PASS; do not claim until user sends results. No DDL or migration. See docs/KAVENEGAR.md for exact Mac commands; PR Draft and main untouched.
