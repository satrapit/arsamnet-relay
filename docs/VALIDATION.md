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
