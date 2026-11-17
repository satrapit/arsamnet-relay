# Arsam Relay

**Current verified QA:** [docs/STATUS.md](docs/STATUS.md) · [Provider go-live gates](docs/PROVIDER_GO_LIVE_GATES.md). Earlier status notes are historical.


Self-hosted, multi-client messaging and notification platform for Laravel 13, PHP 8.3+ and MySQL 8, designed for cPanel shared hosting.

**Development foundation only — NOT production ready.** No real SMS/email/webhook sending is enabled.

## Repository

- https://github.com/satrapit/arsamnet-relay (**currently PUBLIC**).
- Product: Arsam Relay. Repository: arsamnet-relay.
- Planned hostname: arsamrelay.arsamnet.com (**not deployed or DNS-verified**).
- Target version: 0.1.0-alpha.1 (**no tag published**).
- Original requirements: docs/MASTER_PROMPT.md. Its older proposed repository address is superseded by this user's actual GitHub URL.
- Canonical proposed schema: docs/ERD.dbml; 27 tables, 192 columns, 32 relationships. Formal schema signoff is pending.
- License decision and public security audit are pending. Never commit production secrets.

## Code now present (initial tests passing; not production-ready)

Official Laravel 13 scaffold; Fortify login/password reset/email verification; admin MFA gate; opt-in CLI Super Admin provisioning; initial roles/clients/API keys/audit table migrations; machine bearer key authorization and optional CIDR allowlist; Super Admin Pennant flags; responsive Tailwind 4 admin foundation with one TailAdmin Free MIT card component and a mobile drawer; local npm Font Awesome webfonts; bounded database queue Cron schedule; initial regression tests.

**Not yet built:** live publishing and delivery, real provider adapters, ACK/NACK, retries/DLQ, secure webhooks, tenant provider configuration, complete TailAdmin Free component integration, full IDS/monitoring, OpenAPI/Postman/HTTPie, localization and production operations.

## Set up locally

Prerequisites: PHP 8.3+ and required extensions, Composer, MySQL 8, Node 22+.

    git clone git@github.com:satrapit/arsamnet-relay.git
    cd arsamnet-relay
    git switch feat/laravel13-foundation
    composer install
    cp .env.example .env
    php artisan key:generate
    npm install
    npm run build

Configure .env DB credentials and APP_URL. Generate a separate audit key locally:

    php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'

Put its output in ARSAM_AUDIT_HMAC_KEY in the PRIVATE .env. Do not commit it.

The first four migrations were applied successfully to a dedicated local MySQL 8.0.33 database after an empty-schema preflight. **Do not rerun initialization or destructive migrations** on that populated database.

For first Super Admin, temporarily enable ARSAM_PROVISION_ADMIN=true, clear any cached configuration and run:

    php artisan relay:provision-admin admin@example.org --name="Platform Administrator"

The command prompts for the password securely. Disable provisioning immediately and enroll TOTP at first sign-in. No public administrator registration.

## Runtime / deployment notes

Only public/ may be the web document root. Build public/build assets off-host; no Node runtime is required by cPanel. To use Scheduler, configure a verified every-minute Cron invoking php artisan schedule:run. No Redis/Supervisor/systemd/permanent process dependency.

Dependency installation and Vite build succeeded on the developer Mac (Composer 133 packages, npm 102 packages). The latest reported Laravel test run passed 35 tests / 108 assertions. All four migrations ran on local MySQL 8.0.33; the two initial schema verifier differences were **missing DBML annotations**, now corrected without SQL changes. Live verification after the correction is pending. The real package lockfiles exist locally but are not yet committed. See docs/VALIDATION.md and docs/LOCAL_DEVELOPMENT.md.

No GitHub Actions workflows were created.

## Isolated smoke tests

Run `php scripts/offline/AuditIntegritySmoke.php` without Composer for the narrow Audit HMAC/redaction test. Run `php artisan relay:verify-audit --limit=1000` after installing dependencies and migrating the approved schema; it detects edits to rows under review but not deletions. See docs/VALIDATION.md for exact test evidence.

## Machine API key lifecycle

Platform admins can issue keys expiring after 7/30/90/365 days, revoke keys, or rotate keys. Rotation replaces the token in one database transaction (the old token is revoked immediately, and the replacement is shown once); the new token expires after 90 days. Operations require recent password confirmation and log a redacted HMAC-tagged audit event. Initial in-memory SQLite Feature tests for key issuance/rotation/tenant isolation PASSED; MySQL integration/concurrency tests remain NOT RUN.

## Client machine IP allowlist

Platform administrators can manage optional source-IP CIDR rules at **Clients → IP allowlist**. Rules are normalized (IPv4/IPv6), limited to 64 per client, require a recent password confirmation to edit and are audited without exposing raw network ranges in the audit payload. **An empty allowlist means no IP restriction**; removal of the final enabled rule requires typing `UNRESTRICT`. Never assume this replaces API-key authentication.

Verification without Composer: `php scripts/offline/IpCidrSmoke.php` (23 assertions passed). For reproducible ERD/migration **name** comparison: `php scripts/check_migration_parity.php`. Run the real Laravel Feature suite and disposable MySQL checks before deploying.

## Local development

For first installation, `.env` creation, database-free Scheduler verification and test/lockfile rules, follow [docs/LOCAL_DEVELOPMENT.md](docs/LOCAL_DEVELOPMENT.md). Do not run migrations on an unverified database.

## Verified first test pass and migration rehearsal gate

The initial preflight verified the dedicated local `arsamnet_relay` schema was empty **before** applying the four migrations. It is now populated; `relay:db-preflight` must no longer pass. Use `relay:db-verify` for read-only post-migration checks. Do not reset/drop shared or current databases.

 
## First successful MySQL migration

On the developer Mac (2026-11-17), all four initial MySQL 8 migrations completed successfully on an initially empty `arsamnet_relay` schema and `migrate:status` reported batch 1 for each. PHPUnit results improved to **31 passing tests / 96 assertions** (on SQLite in-memory). Run the new **read-only** `php artisan relay:db-verify --expect=arsamnet_relay` for a follow-up check of tables/columns, key indexes and foreign keys. This has not been run yet, and the overall product remains pre-release. Do not reset the migrated database.

 
## Current schema and quality gates (2026-11-17)

The latest Mac run reports **35 passing PHPUnit tests / 108 assertions**, with all four migrations still `Ran`. The initial read-only MySQL verifier found two direct client foreign keys present in MySQL but missing from DBML. The DBML now documents **32 FK relationships**; no migration/schema change was needed. Run `php scripts/check_migration_relationships.php` and `php artisan relay:db-verify --expect=arsamnet_relay` to confirm the corrected source against the database. Pint reported 20 formatting issues; run `./vendor/bin/pint` locally, verify with `./vendor/bin/pint --test`, rerun PHPUnit and commit the resulting formatting changes plus the reviewed real Composer/npm lockfiles.


## PHP-only QA checks

All offline design/migration checks now run in **PHP**; no Python runtime or packages are required. Execute:

```bash
php scripts/validate_design.php
php scripts/check_migration_parity.php
php scripts/check_migration_relationships.php
php scripts/offline/AuditIntegritySmoke.php
php scripts/offline/IpCidrSmoke.php
php artisan test
php vendor/bin/pint --test
php artisan relay:db-verify --expect=arsamnet_relay
```

The first five commands do not require Composer or a database. `php artisan test` runs configured SQLite in-memory tests; the last schema check queries a **local MySQL schema read-only** and expects `APP_ENV=local`. Use `php artisan config:clear` before Artisan verification. The older Python scripts are intentionally removed; the common `App\Support\OfflineDesignChecks` class is covered by PHPUnit negative/positive tests. Node/npm remain necessary for **building** frontend assets, not for these PHP checks.


## Opt-in publish staging (development only)

A first Milestone 3 draft adds `POST /api/v1/messages` with tenant-scoped target IDs, `messages.publish` scope, HMAC idempotency, encrypted message storage and native DB jobs carrying delivery IDs only. **Disabled by default**: `ARSAM_PUBLISH_ENABLED=false`. No live SMS/email/webhook dispatch, ACK, retries, provider receipt or actual delivery exists. New PHP feature tests have been authored but **not yet run on the developer Mac**. See [docs/API.md](docs/API.md) and [docs/QUEUE.md](docs/QUEUE.md).

 
## Isolated MySQL integration QA

The real MySQL transaction and two-process idempotency test harness is now
available in PHP at `scripts/mysql_publish_integration.php` with a protected
one-time schema creator `scripts/mysql_publish_prepare.php`. It uses the
dedicated schema `arsamnet_relay_integration_test` and refuses to touch
`arsamnet_relay`. No destructive reset or actual provider send is involved.
**The Mac MySQL integration run is still pending.** See
[docs/MYSQL_INTEGRATION.md](docs/MYSQL_INTEGRATION.md) for safe one-time setup.

 
## Lease fencing groundwork

The internal PHP `DeliveryLeaseManager` can atomically claim a staged
delivery for one tenant, renew only with its live fencing token, and move
an expired/uncertain lease to manual review rather than sending again.
It does not contact providers or expose new routes. New PHP unit/feature
and isolated MySQL lease tests are written but **not yet run on Mac**.
See [docs/QUEUE.md](docs/QUEUE.md) and
[docs/MYSQL_INTEGRATION.md](docs/MYSQL_INTEGRATION.md).


## Provider attempt bookkeeping (not a sender)

An internal, provider-free PHP service persists a `started`
`delivery_attempts` record under a current fenced lease and an authorized
tenant provider route. It can normalize accepted/rejected/retryable/ambiguous
outcomes into local delivery states, but **cannot mark `delivered`** and
never sends any SMS, email or webhook. Automatic retry remains disabled.
New PHP and isolated MySQL checks are authored but have **not yet run**
on the developer Mac. See [docs/PROVIDERS.md](docs/PROVIDERS.md).


## Fake-only callback signature/replay exercises (dev only)

A local/test-only `FakeSignedCallbackReceipt` service demonstrates a
strictly HMAC-authenticated receipt for a `fake-only` provider, event
HMAC deduplication, tenant/attempt correlation and atomic
`accepted -> delivered`. There is **no public callback route**, no
real provider implementation and no new migration. New PHP/MySQL
regressions have been authored but not yet executed on the developer Mac.
See [docs/PROVIDERS.md](docs/PROVIDERS.md).


## Fake-only end-to-end dispatch verification (development)

An internal `FakeDispatchCoordinator` provides a deliberately
local/testing-only path for simulating a provider acceptance after
publish/stage/lease/durable attempt. The previously added signed
fake callback independently verifies delivery before marking
`delivered`. Neither component exposes a public route or does
outbound network I/O. Seven new PHP Feature tests and an isolated
MySQL test extension have been written but await the next Mac run.


## Transport-neutral provider adapters (contract only)

The PHP `OutboundProviderAdapter` interface declares channel,
capabilities and a single normalized send contract for future SMS,
SMTP and webhook transports. The only implementation today is
`FakeOutboundProviderAdapter`, which is **strictly local/testing-only**
and performs no external I/O. Sensitive request objects reject
serialization and redact debug output. New unit tests are written
but have not run on Mac. No real provider delivery is enabled.
See [docs/PROVIDERS.md](docs/PROVIDERS.md).


## Fake transport contract integration

The internal local/testing-only coordinator now actually uses the typed
`FakeOutboundProviderAdapter` after persisting a delivery attempt. Its
request contains the authorized target and message content only in memory
and cannot be serialized to queue jobs. No real network traffic or
production sender is enabled. New PHP/MySQL regression validation pending.


## Read-only provider selection

A new `ProviderRouteSelector` chooses the first eligible account for
an unleased, unattempted staged delivery by client, channel, enabled
route/account, message validity and configured priority. No credentials
are decrypted, messages sent or automatic retries scheduled. Nine new
PHP Feature tests and one isolated MySQL checkpoint are authored but
not yet run on Mac. See [docs/PROVIDERS.md](docs/PROVIDERS.md).


## Manual first-route fake dispatch (local/testing only)

`FakeDispatchCoordinator::dispatchFirstEligible` uses the read-only
provider priority selector to choose one eligible account for a staged
unattempted delivery, then performs an entirely fake, no-network
dispatch. A failed account is never automatically replaced with the
next provider. A future-scheduled message cannot be dispatched early.
New PHP and independent MySQL regressions are authored but await Mac QA.
