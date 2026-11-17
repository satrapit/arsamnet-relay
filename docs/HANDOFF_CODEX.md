# Codex handoff — 2026-11-17

Repository: https://github.com/satrapit/arsamnet-relay (PUBLIC). Branch: feat/laravel13-foundation; main remains initialized only. Product Arsam Relay. No deployment or release tag.

## Current status

- Original source specification is docs/MASTER_PROMPT.md. Proposed schema docs/ERD.dbml has 27 tables, 192 columns and 30 references. Four migration files are drafted, but not approved or tested.
- Laravel 13 source, Fortify/Pennant configuration, admin MFA gate, protected CLI Super Admin, clients/key issuance/revocation, optional CIDR-bound machine API, global flags, responsive views and tests are committed.
- Laravel dependencies, database tests, Vite build, official dbdiagram import, full TailAdmin Free integration, Graphify install, actual send adapters and cPanel deployment have NOT run.
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
- Run `python3 scripts/check_migration_parity.py` to detect table/column name drift; its parser passed good/bad **synthetic fixture** tests. An independent read-only GitHub source comparison showed 27/27 table names and 192/192 column names aligned across DBML and current migrations; official DBML parsing, types, defaults, indexes and MySQL execution remain NOT RUN.
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
