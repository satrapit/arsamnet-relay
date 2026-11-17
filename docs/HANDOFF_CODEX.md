# Codex handoff — 2026-11-17

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
