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

 
## Independent PHP-on-MySQL integration gate (pending)

Unlike normal `php artisan test`, which deliberately forces ephemeral
SQLite, `php scripts/mysql_publish_integration.php
--expect=arsamnet_relay_integration_test` runs actual MySQL
transaction/rollback and simultaneous PHP-process idempotency checks. It
refuses anything but the explicitly prepared and empty isolated schema.
It never runs migrations or reset commands. See `docs/MYSQL_INTEGRATION.md`
for safe setup and command examples. **Not yet executed on the developer Mac.**

 
## Delivery lease tests — new, execution pending

`php artisan test --filter=DeliveryLeaseManagerTest` verifies tenant
ownership, claim fencing, wrong/stale token rejection, disabled/suspended
owners, message expiration, bounded TTL, quarantine to manual review,
expiry-capped deadlines and prevention of provider/queue side effects.
The expanded `scripts/mysql_publish_integration.php` adds real MySQL
lease/renew/quarantine assertions to the previously successful isolated
concurrency and rollback run.

**Mac test result pending** for the new files. Use `php artisan test` and
`php vendor/bin/pint --test` as the full PHP QA gates. The MySQL test should
be rerun against the **already migrated** `arsamnet_relay_integration_test`
schema only; no new schema/migration/reset is required.


## Lease PHP test results (2026-11-17)

On developer Mac, `php artisan test --filter=DeliveryLeaseManagerTest` passed 10 tests/41 assertions and the complete in-memory PHPUnit suite passed **62 tests/207 assertions**. PHP syntax checks passed for new source/test/integration files; Pint passed all 76 PHP files after correcting one styling issue in the new lease test. Real-MySQL lease claims/renewals/quarantine in the updated integration runner remain to be checked. No live provider sends were attempted.

 
## Confirmed real MySQL lease checks

Expanded isolated MySQL integration checks have now **PASSED on developer
Mac**, including claim/renew with fencing token and manual-review quarantine
after expiry. Separately, 62 Laravel tests/207 assertions and 76-file Pint
were successful. Next tests should cover deliberate durable provider attempt
recording and outcome transitions, still without external sends.


## Durable provider attempt tests — new source pending Mac verification

`php artisan test --filter=DeliveryAttemptManagerTest` contains ten
PHP Feature tests for: one durable started attempt; accepted-not-delivered;
rejection; retryable/ambiguous quarantine; cross-tenant account route abuse;
disabled route/account; stale fencing tokens; rejection of identifying
provider references and invalid HTTP statuses; suspended client block.

`scripts/mysql_publish_integration.php` now additionally persists a fake
provider account/route, runs the already proven Publish/Stage/Lease sequence,
records a durable started attempt, normalizes accepted and ambiguous results,
rejects duplicate completion and automatic retries, then deletes only those
fixtures. It still executes only on the dedicated pre-migrated, empty MySQL
integration schema. The expanded runner's **new attempt checks are NOT RUN**
on the developer Mac. No provider sends or schema alterations.


## Confirmed provider-attempt regression — developer Mac

All ten `DeliveryAttemptManagerTest` cases passed (50 assertions), the full PHP test suite passed **72 tests/257 assertions**, and Pint scanned **79 PHP files** successfully. The extended MySQL integration CLI also passed on the isolated schema, including durable `started` attempts and normalized accepted/ambiguous results with no provider I/O. The test database was returned to an empty fixture state. One formatter-only change in the integration script awaits a local Git commit. Callback verification and end-recipient delivery state changes remain outside this verified milestone.


## Signed fake callback tests — execution pending

`php artisan test --filter=FakeSignedCallbackReceiptTest` contains nine
PHP Feature cases: valid fake signed receipt (the sole permitted
`accepted -> delivered` transition), event replay/second event denied,
bad/tampered signature, stale/future timestamp, unmatched provider ref,
cross-tenant account mismatch despite a misconfigured route,
disabled route/non-fake adapter, nonaccepted attempt and
`production` environment refusal.

Existing guarded PHP MySQL runner now also verifies receipt persistence,
delivery state, tamper/replay resistance and FK-safe cleanup. This new
code **has not been run on Mac**. Existing 72-test/257-assertion suite
and earlier MySQL acceptance remain the prior verified baseline.


## Fake signed receipt verified (Mac, 2026-11-17)

Full PHP regression: **81 tests / 292 assertions PASS**. Independent PHP on real MySQL PASS for fake HMAC callback delivery proof, event replay rejection and tamper resistance, plus all previous transaction, lease and attempt checkpoints. Integration fixture cleaned to empty, primary schema read-only verification PASS. No public callback endpoint, real provider cryptographic contract or production call was tested.


## Full fake-only publish-to-verified-receipt path — execution pending

`php artisan test --filter=FakeDispatchCoordinatorTest` tests seven
cases: publish/stage/lease/durable fake acceptance then separately signed
callback; repeat dispatch refusal; cross-tenant isolation; disabled route
and non-fake provider; invalid encrypted callback secret; expired message;
and production-environment refusal before any lease/attempt.

The existing isolated `scripts/mysql_publish_integration.php` now
has an extra fake-only end-to-end message, validated for accepted-before-
delivered, no duplicate dispatch, signed receipt correlation, one
attempt/receipt and FK-safe cleanup. No new schema or migrations required.
Both the new PHPUnit cases and the updated MySQL runner are **NOT RUN**.


## Fake end-to-end integration QA executed

Developer Mac confirmed 7 new FakeDispatchCoordinator tests PASS (39 assertions), full suite 88 tests PASS (331 assertions), Pint 83 PHP files PASS. The standalone guarded MySQL CLI also PASS for complete fake-only publish/stage/lease/attempt/accepted/signed receipt/delivered and cleanup to empty. Live provider contract testing, real HTTPS/network delivery and production deployment are not yet implemented.
