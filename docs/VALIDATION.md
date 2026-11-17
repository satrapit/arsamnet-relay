# Verification ledger — 2026-11-17

| Check | Result | Notes |
| --- | --- | --- |
| GitHub repository and push permissions | PASS | satrapit/arsamnet-relay is user owned, publicly visible |
| Development branch and commits | PASS | feat/laravel13-foundation created and updated |
| Official Laravel 13 source retrieval | PASS (metadata) | Via GitHub connector |
| ERD + data dictionary in GitHub | PASS | 27-table baseline |
| Per-table CSV generation | PASS (structure only) | 27 files, 192 columns |
| App PHP lint/Composer validation | NOT RUN | Composer missing, required PHP extensions unavailable |
| composer install + lockfile | NOT RUN | No Packagist DNS |
| npm install/build + lockfile | NOT RUN | No package registry DNS |
| Laravel PHPUnit, Pint, DB migrations, rollback | NOT RUN | Dependencies unavailable, schema unapproved |
| Official dbdiagram import | NOT RUN | No account session |
| TailAdmin Free integration | PARTIAL | Custom Tailwind UI; upstream components not imported yet |
| Provider send/queue DLQ and security | NOT IMPLEMENTED | Milestones 3–4 |
| Graphify / cPanel Cron/SSL/DNS | NOT RUN | No connected host |
| Public release/version tag | NOT CREATED | No release gate passed |
| GitHub Actions workflows | NOT CREATED | Explicit requirement |

Committed code is NOT equivalent to tested functionality. No production rollout occurred.

## Follow-up implementation and narrow checks

- PASS: design-pack offline Python validator executed in isolated environment (27 tables, 192 columns, 30 references, 27 CSV examples).
- PASS: PHP `php -l` performed on the exact drafted source for SecurityAudit, SecurityEventRecorder and SecurityMonitorController. No Laravel runtime dependencies were used in those checks.
- PASS: Node `node --check` run on the mobile drawer JavaScript before committing.
- NOT RUN: PHP lint for the entire GitHub branch, PHPUnit, MySQL migrations, Composer and npm installs, Vite build and browser interaction tests.
- Current security monitor is read-only and depends on the configured `ARSAM_AUDIT_HMAC_KEY` and migrated database.

- PASS: `php scripts/offline/AuditIntegritySmoke.php` executed against a temporary local source mirror using minimal stubs: six audit assertions passed. No Composer/Laravel or MySQL code was executed.
- PASS: `php -l` on security audit implementation, event recorder, monitor controller, verification command and offline smoke test.
- NOT RUN: Official DBML parser; MySQL schema/migrations; complete PHP lint and Laravel/PHPUnit suite; Vite production build and cPanel smoke checks.

- PASS: PHP syntax check (php -l) on the newly authored ClientApiKeyController and ApiKeyLifecycleTest, using an exact local source copy before the GitHub write.
- NOT RUN: Laravel feature tests for API key lifecycle or concurrency on MySQL 8; these remain release blockers.

## Continued foundation hardening — 2026-11-17

| Check | Status | Evidence/limits |
| --- | --- | --- |
| IPv4/IPv6 normalization and CIDR matching | PASS | `php scripts/offline/IpCidrSmoke.php` — 23 assertions, no framework/database |
| PHP syntax for newly authored CIDR/controller/tests | PASS | `php -l` on IpCidr, IpCidrSmoke, ClientIpAllowlistController, ClientIpAllowlistTest in local authoring container |
| DBML vs draft migration table and column names | PASS (static comparison) | Live GitHub file content analysis found 27 matching tables, 192 matching columns; no extras |
| Schema-parity CLI good and bad fixtures | PASS (synthetic) | `python3 scripts/check_migration_parity.py --root <fixture>`; correct schema passed; drift failed |
| Server Laravel Feature tests / MySQL migrations | NOT RUN | Composer and PHP extensions (mbstring, pdo_mysql, pdo_sqlite) absent; no MySQL test DB |
| Dependency resolution / lockfiles / Vite build | NOT RUN | Registry access unavailable |
| DBML official import, FK/type/default parity | NOT RUN | Only names were compared |
| cPanel/CloudLinux live network/Cron/TLS | NOT RUN | No host access |

No schema signoff, version tag or production readiness claim.

## Real developer Mac dependency/bootstrap results (reported 2026-11-17)

- PASS: PHP 8.3.30, Composer 2.9.5, Node 24.13.0, npm 11.6.2 detected.
- PASS: `composer validate` accepted composer.json with a license warning (addressed in following source revision; not yet rerun).
- PASS: `composer install --prefer-dist` installed 133 dependencies, Laravel 13.42.0, Fortify 1.42.0, Pennant 1.27.0, and wrote a LOCAL composer.lock.
- PASS: `npm install` (102 packages, audit reported no vulnerabilities) wrote a LOCAL package-lock.json; `npm run build` succeeded in Vite 8.3.8. fontaine notice informational only.
- PASS: `php artisan about` bootstrap and `php artisan route:list` (42 routes).
- NOT PASS: initial `php artisan schedule:list` because default database cache tried to access a nonexistent SQLite database file; `CACHE_STORE=array php artisan schedule:list` is a non-DB diagnostic to run next.
- NOT RUN: `artisan test`/PHPUnit, MySQL migrations or rollback, Composer security audit, deployment, final licensing/security signoff.
- FIXED IN SOURCE (needs rerun): standalone Audit/IpCidr smoke scripts moved to scripts/offline/ to avoid Composer PSR-4 scanning; provisional proprietary license added. PHPUnit config now forces ephemeral sqlite :memory: for test safety.
- LOCKFILES: composer.lock and package-lock.json exist only in user's local checkout until explicitly reviewed/committed/pushed.


## Follow-up developer output — unit/feature tests and MySQL connectivity (2026-11-17)

- PASS: `php artisan config:clear`; `composer validate --strict`; optimized Composer autoload generated with no PSR-4 warnings; both offline smoke suites (Audit 6, CIDR 23).
- PASS: `CACHE_STORE=array php artisan schedule:list` lists the minute heartbeat and bounded queue worker; `PDO::getAvailableDrivers()` includes MySQL and SQLite.
- PASS: PHPUnit Unit 2 tests / 8 assertions.
- PARTIAL: PHPUnit Feature 7 passed, 1 failed, 18 skipped by `--stop-on-failure` (35 assertions in output). The stock ExampleTest expected 200 from root; project redirects root to dashboard. Test expectation updated and PHP syntax checked locally, but not yet re-executed on the developer Mac.
- PASS (reported): `composer audit --locked` reported no known security vulnerability advisories.
- BLOCKED: `php artisan db:show` and `migrate:status` returned MySQL error 1049 for `arsamnet_relay` at 127.0.0.1:3306. Developer reports a database exists and is configured; host/port/name must be reconciled read-only before any migrations.
- SOURCE FIX: stop tracking `public/build/.gitignore`, which Vite had removed locally; root .gitignore already excludes generated build assets.
- NOT RUN: Full successful Feature suite, actual MySQL migrations/rollback, package lockfile commits, deploy. Draft PR remains unmerged.


## Full Mac Feature pass and real MySQL connection (2026-11-17)

- **PASS (user log):** PHPUnit Feature **26 tests / 82 assertions** with no failures; Unit **2 tests / 8 assertions**. Together **28 tests / 90 assertions**. These tests use SQLite `:memory:`, NOT MySQL 8.
- **PASS (user log):** MySQL 8.0.33 available at 127.0.0.1:3306 and `DB_DATABASE=arsamnet_relay`. The previous 1049 error was resolved by replacing a hyphen with an underscore in the actual database name. `php artisan db:show` now connects.
- The **393 tables / 510.86 MB** displayed by `db:show` span **other schemas visible to the root user** (e.g. other unrelated projects); they do NOT establish the table count of `arsamnet_relay`.
- `php artisan migrate:status` reports `Migration table not found`, expected before first migration; it does not establish whether the target schema contains unrelated tables.
- **Fix in new branch commit:** Removed Laravel scaffold DatabaseSeeder example user, eliminating a known/default account created by `db:seed`. Added fail-closed, read-only `php artisan relay:db-preflight --expect=arsamnet_relay` command to check local env, no config cache, MySQL 8, exact database and **zero** tables/views.
- **Fix in new branch commit:** `composer setup` no longer overwrites an already set `APP_KEY`; changing it would invalidate encrypted database values.
- **NOT YET RUN:** Three newly written bootstrap-safety Feature tests, actual database preflight, migrations against MySQL 8.0.33, foreign-key constraints on MySQL, rollback, final schema approval.

 
## First real MySQL migration PASS and metadata verification pending — 2026-11-17

- PASS (user Mac log): `php artisan test` completed **31 tests and 96 assertions**, with zero failures. These still use SQLite in memory, not MySQL.
- PASS (user Mac log): `relay:db-preflight --expect=arsamnet_relay` verified a fresh MySQL 8 schema with **0 tables/views** before any migration.
- PASS (user Mac log): `php artisan migrate --database=mysql` created the Laravel `migrations` table and ran **all four project migrations** successfully in batch 1. `php artisan migrate:status` confirms all four are `Ran`.
- The four migrations are designed to create 27 project tables plus 1 Laravel migration ledger; **the exact actual database table/FK/index counts have not yet been independently inspected**.
- SOURCE ADDED (unexecuted on Mac): `SchemaManifest` parser for the supported local DBML subset and read-only `php artisan relay:db-verify --expect=arsamnet_relay` checking exact table names, documented column types/lengths/required fields, declared unique/primary and ordinary indexes, 30 FK definitions and restrict-on-delete behavior, and four migration entries.
- SOURCE ADDED (unexecuted on Mac): `SchemaManifestTest` (2 Unit cases) and `DatabaseSchemaVerificationSafetyTest` (2 Feature cases) protect manifest parsing and verifier failure in non-local/test environments.
- NOT RUN: the newly authored four tests, read-only MySQL schema verifier, MySQL rollback, concurrent inserts/rotations against MySQL, production/cPanel rehearsal. **Do not perform destructive reset on the now-populated schema.**
