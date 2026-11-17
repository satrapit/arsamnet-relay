# Testing and quality gates

Committed initial negative tests: guest dashboard denial, disabled registration, mandatory MFA gate, support role denial for global flags, machine token tenant binding, revocation/expiry/suspension and CIDR tests, and HTTP security headers.

Recommended local commands:

    composer validate
    composer install
    php artisan about
    php artisan route:list
    php artisan schedule:list
    php artisan test
    ./vendor/bin/pint --test
    composer audit
    npm install
    npm run build

Do NOT run migrate:fresh against a real/non-disposable database. First review DBML, then rehearse MySQL migrations, rollback, composite FKs and schema drift with official package migrations. Record actual command output and environment versions. The code has not been PHP-linted or executed in this environment.

## Dependency-free smoke test

Run `php scripts/offline/AuditIntegritySmoke.php` to exercise the actual audit class against narrow in-memory stubs for Laravel's DB facade and Carbon. It checks HMAC verification, redaction, IP hashing and tamper detection. It is **not** a substitute for integration tests with vendor packages or MySQL 8.

A separate `php -l` check passed for SecurityAudit, SecurityEventRecorder, SecurityMonitorController, VerifySecurityAudit, and the offline smoke test source before those files were committed.

## CIDR tests (2026-11-17)

Run `php scripts/offline/IpCidrSmoke.php`: 23 checks passed offline on PHP 8.4.24 for valid/invalid IPv4/IPv6, canonicalization, /0 rejection, masks and mixed-family failures. This executes the actual `App\Support\IpCidr` class without a database. ClientIpAllowlistTest and MachineApiTest extend this coverage at HTTP/database level but have not been executed.

Run `php scripts/check_migration_parity.php` for a **names-only** DBML/migration comparison. This script does not replace MySQL migration/rollback, FK/index checking or official DBML import. The parser itself was exercised with a correct and deliberately-drifted miniature fixture.

## First Composer installation smoke checks

User-reported macOS installation (2026-11-17): PHP 8.3.30, Composer 2.9.5, Node 24.13.0 and npm 11.6.2. Composer resolved 133 packages (Laravel v13.42.0, Fortify v1.42.0, Pennant v1.27.0); npm installed 102 packages and Vite production build passed; `artisan about` and 42 route registrations passed. `schedule:list` failed because no `.env`/SQLite database existed with `CACHE_STORE=database`. This does not establish any scheduler defect. See docs/LOCAL_DEVELOPMENT.md. The standalone PSR-4 warning was addressed by moving offline scripts to scripts/offline/. Actual PHPUnit, MySQL migrations and `CACHE_STORE=array artisan schedule:list` remain unverified until new user output.

 
## Latest complete Mac tests and formatting follow-up

The developer has reported **35 passing PHPUnit tests / 108 assertions** after the read-only DB schema verifier was added. The validator initially found two DBML-only omissions (the subscriptions/deliveries direct client foreign keys, already in MySQL); source documentation and verification expectations were updated to 32 total references.

Use `php scripts/check_migration_relationships.php` to compare PHP migration FK declarations with DBML. A separate read-only MySQL check `php artisan relay:db-verify --expect=arsamnet_relay` must be rerun after pulling the updated DBML, before claiming full parity.

Pint `--test` reported 20 style issues across 61 scanned PHP files. Run `./vendor/bin/pint` on Mac for autofix, rerun `--test`, then PHPUnit. Formatting does not require or justify a database migration.

 
## PHP-first test policy (2026-11-17)

Use PHP for all testing and schema/ERD validation unless a non-PHP tool is technically indispensable. **No Python is required.** The former Python validation scripts were retired, and the PHP-only equivalents run without Composer:

```bash
php scripts/validate_design.php
php scripts/check_migration_parity.php
php scripts/check_migration_relationships.php
php scripts/offline/AuditIntegritySmoke.php
php scripts/offline/IpCidrSmoke.php
```

When Composer dependencies exist, use `php artisan test` for PHPUnit and `php vendor/bin/pint --test` to check PHP formatting. `php artisan relay:db-verify --expect=arsamnet_relay` is a separate read-only inspection of the configured local MySQL database. A new `tests/Unit/OfflineDesignChecksTest.php` covers positive schema matches and fail-closed behavior on unsupported migration declarations; test results from the developer Mac are still pending.

**External runtime exception:** building Tailwind/Vite assets requires Node/npm. This does not require Python for tests.

 
## Scalar versus composite foreign-key checks

The PHP-only migration relation checker accepts both `$table->foreign('delivery_id')->references('id')` and `$table->foreign(['message_id', 'client_id'])->references(['id', 'client_id'])`, with `restrictOnDelete()` required. The first Mac run failed on a valid scalar declaration; this parsing bug has been fixed and targeted PHPUnit regression tests added.

After pulling, run `php scripts/check_migration_relationships.php`, `php artisan test`, and `php vendor/bin/pint --test`. The Mac's independent read-only MySQL structure verification already passed and requires no migration changes. Report the new result rather than assuming the fix is verified on Mac.


## Publish staging feature tests — pending on developer Mac

`php artisan test --filter=PublishMessagesTest` covers: disabled-by-default gate, encrypted local persistence, ID-only database queue job payloads, exact idempotency replay without duplicate jobs, conflicting replay, tenant/target isolation, scope restrictions, invalid headers and duplicate targets, deliberately inert job handling, and client-separated idempotency. These tests do not deliver SMS or email. Run `php artisan test` for the full regression suite and `php vendor/bin/pint --test` for formatting. For MySQL concurrency and worker failure handling use a **separate disposable MySQL integration database**, not the already migrated development schema.


## First Publish runtime result

The first Mac execution of `tests/Feature/PublishMessagesTest.php` passed all nine tests (48 assertions). Full SQLite PHPUnit result was 52 tests/166 assertions. A separate read-only MySQL schema check also passed. No real dispatch/provider or concurrent MySQL publishing was tested. The next QA gate is `php vendor/bin/pint --test` and a full regression rerun after formatting; then commit and push the developer's local Pint edits and actual generated Composer/npm lockfiles.

 
## Publish/Pint stabilization — developer Mac confirmed

- Full PHP test suite: **52 tests, 166 assertions, all PASS**.
- `php vendor/bin/pint --test`: **72 PHP files, all PASS** after automated formatting.
- Read-only MySQL 8 schema verification: PASS; **28 tables including migrations, 192 fields, 61 indexes and 32 restricted FKs**.
- Strict Composer validation and Git whitespace check: PASS.

These results cover in-memory SQLite runtime and MySQL metadata, **not** MySQL concurrent publish durability. Lockfile/formatting Git commits are still pending.
