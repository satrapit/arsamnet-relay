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
- Run `php tests/offline/AuditIntegritySmoke.php` for an isolated no-Composer integrity smoke check (verified in the authoring container). **It does not test real Laravel or MySQL**.
- Run `php artisan relay:verify-audit --limit=1000` only after dependencies, migrations and ARSAM_AUDIT_HMAC_KEY are configured; this only detects edits to inspected rows, not deleted records.
- Full PHP lint and PHPUnit tests remain NOT RUN; do not merge before running them on the actual development environment.

## Key rotation continuation

Read the updated ClientApiKeyController and tests/Feature/ApiKeyLifecycleTest.php. Confirm that the database transaction and lockForUpdate prevent concurrent rotation and prevent key ID crossing tenant boundaries. Verify expiry rejection and immediate revocation using MySQL 8, and confirm token display never leaks to logs/cache. Tests are authored, NOT executed in Laravel yet.
