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

Run `python3 scripts/check_migration_parity.py` for a **names-only** DBML/migration comparison. This script does not replace MySQL migration/rollback, FK/index checking or official DBML import. The parser itself was exercised with a correct and deliberately-drifted miniature fixture.

## First Composer installation smoke checks

User-reported macOS installation (2026-11-17): PHP 8.3.30, Composer 2.9.5, Node 24.13.0 and npm 11.6.2. Composer resolved 133 packages (Laravel v13.42.0, Fortify v1.42.0, Pennant v1.27.0); npm installed 102 packages and Vite production build passed; `artisan about` and 42 route registrations passed. `schedule:list` failed because no `.env`/SQLite database existed with `CACHE_STORE=database`. This does not establish any scheduler defect. See docs/LOCAL_DEVELOPMENT.md. The standalone PSR-4 warning was addressed by moving offline scripts to scripts/offline/. Actual PHPUnit, MySQL migrations and `CACHE_STORE=array artisan schedule:list` remain unverified until new user output.
