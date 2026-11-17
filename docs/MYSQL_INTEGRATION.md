# Real MySQL publish integration — isolated and PHP-only

Status: **test source added, not yet run against developer MySQL**. This is
independent of PHPUnit's intentionally forced SQLite `:memory:` connection.
It never contacts SMS, SMTP, webhook or any other provider.

## Preconditions and safety

- Developer Mac, APP_ENV=local and uncached Laravel configuration.
- Existing project `arsamnet_relay` is untouched.
- Create the exact dedicated schema **arsamnet_relay_integration_test** only.
- The preparation script refuses any existing schema and never drops a database.
- The test runner refuses the main schema, production/staging, non-MySQL-8
  servers, mismatched selected schemas, incorrect queue connection, missing
  migrations, and **any preexisting rows in the 27 project tables**.
- The runner uses only PHP, Laravel's installed dependencies, Symfony Process,
  and MySQL. It executes `SELECT GET_LOCK` to guard concurrent test runs.
- PHP unit/feature tests continue to use SQLite in memory as before.
- No `migrate:fresh`, `db:wipe`, `rollback`, TRUNCATE or DROP is part of
  this test workflow. The runner uses fixture-specific DELETE and rolls back
  its first test transaction. If it fails to clean up, stop and inspect
  the isolated schema; **do not run it again against nonempty tables**.

## One-time setup

In a Mac terminal with the repository checked out and `.env` pointing at
`arsamnet_relay` on the local MySQL server:

```bash
cd ~/Sites/arsamnet-relay
git pull --ff-only origin feat/laravel13-foundation
php artisan config:clear
php -l scripts/mysql_publish_prepare.php
php -l scripts/mysql_publish_integration.php

# Exactly one new empty schema; no changes to arsamnet_relay.
php scripts/mysql_publish_prepare.php --create=arsamnet_relay_integration_test

# Check exact empty integration schema before its first migration.
DB_DATABASE=arsamnet_relay_integration_test \
  php artisan relay:db-preflight --expect=arsamnet_relay_integration_test

# Only after preflight PASS, create the test schema tables (once).
DB_DATABASE=arsamnet_relay_integration_test php artisan migrate --database=mysql
```

If the integration schema already exists, the create script stops without
changing it. If preflight reports any tables, **do not migrate it blindly**.
A successful first migration creates 27 project tables and Laravel's migration
ledger (28 total). The test runner verifies the actual schema metadata
read-only before inserting fixtures.

## Run real transaction/idempotency checks

```bash
DB_DATABASE=arsamnet_relay_integration_test \
  php scripts/mysql_publish_integration.php --expect=arsamnet_relay_integration_test
```

The runner:

1. Confirms the 28-table/32-FK schema and all project tables empty.
2. Creates encrypted test targets, a client and an API-key record inside a
   transaction, publishes to the real native database queue, rolls back and
   verifies **messages, deliveries and jobs all disappeared**.
3. Publishes once and replays the same key, requiring one message/job.
   Reusing that key with changed payload must fail.
4. Calls `StageDeliveryJob` without a provider; verifies local staging only.
5. Starts two independent PHP processes using a temporary file barrier.
   Both publish the same idempotency key to the same client simultaneously.
   Requires exactly one creation, one replay, one message/delivery and no
   extra queue job.
6. Removes its own fixture rows and matching queued jobs, verifies all
   project tables are empty again and leaves migrations intact.

**Do not run a separate queue worker or scheduler while this integration test
is running.** It is an offline integration fixture exercise, not a live delivery
test or a full concurrency/load benchmark.

## After the run

```bash
php artisan test
php vendor/bin/pint --test
php artisan relay:db-verify --expect=arsamnet_relay
git status --short
```

The last schema verification deliberately selects the **original main
development schema**, not the test schema. No provider sends or queue
consumption is enabled by this integration test. Report the complete output
of any failed step before making schema changes.

 
## Executed baseline and incremental lease test (2026-11-17)

On the developer Mac, the one-time schema creator, empty-schema preflight and **four migrations** succeeded. The original PHP MySQL transaction/idempotency runner passed each of its steps, including two simultaneously launched PHP processes; it returned the schema to an empty fixture state. The schema and original migration ledger remain available for **rerunning** this test.

New source (not yet executed on Mac) extends the SAME integration runner with:

- Claiming the staged delivery through `DeliveryLeaseManager`, with a UUID fencing token.
- Rejecting a second claim, stale/wrong token renewal and quarantining an active lease.
- Successfully renewing a live lease, then simulating an expired timestamp and denying further renewal.
- Transitioning the expired lease to `review_required` with `LEASE_EXPIRED_UNCERTAIN`, without permitting automatic re-claim or creating any provider attempt.

Re-run only the existing integration test; **do not run the schema creator, preflight or migrations again** because the schema now exists and migrations are already installed:

```bash
cd ~/Sites/arsamnet-relay
git pull --ff-only origin feat/laravel13-foundation
php artisan config:clear
php -l app/Services/DeliveryLeaseManager.php
php -l tests/Feature/DeliveryLeaseManagerTest.php
php -l scripts/mysql_publish_integration.php

php artisan test --filter=DeliveryLeaseManagerTest
php artisan test
php vendor/bin/pint --test

DB_DATABASE=arsamnet_relay_integration_test \
  php scripts/mysql_publish_integration.php --expect=arsamnet_relay_integration_test
```

All tests use PHP. The standalone MySQL runner rejects preexisting project rows and runs only on the explicitly selected local integration schema. Stopping a worker after any hypothetical external send could leave uncertainty; consequently this prototype does **not** support releasing timed-out leases back to a sendable queue.

 
## Expanded lease runner executed on Mac — 2026-11-17

The developer reran the same PHP integration script on the existing isolated
MySQL 8 test schema. All checkpoints **PASSED**, including fenced lease claim,
correct-token renewal, expired-token rejection, quarantine to
`review_required`, and two competing PHP processes. The isolated project's
fixture tables returned to an empty state; four migrations remain installed.
This is one test scenario, not load/stress or a proof of crash recovery.


## New attempt lifecycle integration checks (source added, not executed)

The same guarded PHP script now also creates a temporary **fake-only**
provider account and explicit per-client route in the isolated integration
database. It records one `started` attempt before any provider send, stores
an `accepted` result without marking `delivered`, rejects a duplicate
finish, then records an `ambiguous` result and places its delivery in
`review_required` without creating another attempt or queue job. The test
cleanup now deletes its fixture's delivery attempts and provider route/account
in foreign-key-safe order.

The previous MySQL lease checks have already passed; **the new attempt
section has not yet been run**. Do not re-create or re-migrate the existing
schema. Do not run any separate worker/scheduler against it during the check.

```bash
cd ~/Sites/arsamnet-relay
git pull --ff-only origin feat/laravel13-foundation
php artisan config:clear

php -l app/Enums/ProviderAttemptOutcome.php
php -l app/Services/DeliveryAttemptManager.php
php -l tests/Feature/DeliveryAttemptManagerTest.php
php -l scripts/mysql_publish_integration.php

php artisan test --filter=DeliveryAttemptManagerTest
php artisan test
php vendor/bin/pint
php vendor/bin/pint --test

DB_DATABASE=arsamnet_relay_integration_test \
  php scripts/mysql_publish_integration.php --expect=arsamnet_relay_integration_test

git diff --check
git status --short
```

On a failure, **stop** before any manual cleanup or retry. Send the full
error output for review. The integration runner itself will refuse a
nonempty fixture database on the next run.


## Attempt lifecycle integration PASS on developer Mac — 2026-11-17

The additional fake-only attempt scenarios have now executed successfully on the isolated MySQL 8 schema. The runner confirmed durable `started` records, accepted without asserting delivered, duplicate finish prevention, ambiguous results quarantined without retransmission and full fixture cleanup. The prior rollback, lease and two-process idempotency checkpoints also passed. No provider API was called. The ordinary `arsamnet_relay` schema was independently verified read-only. This is an integration result, not a live provider contract or load-test result.


## Fake signed callback scenario — new code not yet run

The already migrated, clean `arsamnet_relay_integration_test` schema is
reused; do not create or migrate it again. The existing PHP runner now
also tests a fake-only encrypted callback secret, a signed event marking
an `accepted` delivery `delivered`, denial of the same replay/tampered
payload, and deletion of dependent `callback_receipts` rows in the
fixture cleanup. This uses no outbound network or real provider.

After pulling the branch, run PHP lint and focused `FakeSignedCallbackReceiptTest`,
the full Laravel suite, Pint and only then the guarded PHP MySQL runner.
Any failure must be inspected before retrying; do not manually wipe the
test schema.


## Confirmed fake callback result (2026-11-17)

Developer Mac execution confirmed the extended PHP MySQL runner passed signed fake callback acceptance, state change to delivered once, replay rejection, payload-tampering rejection and full FK-safe fixture cleanup. All prior transaction/idempotency/attempt/lease checks also passed. Do not confuse this with a third-party provider's real webhook authentication or load-test.


## Fake-only full message lifecycle (new, not yet executed)

The existing isolated PHP MySQL runner now adds one independently
published message, runs `StageDeliveryJob`, calls the internal
`FakeDispatchCoordinator` and verifies it durably produces exactly
one `accepted` attempt (not delivered). It denies a duplicate
coordinator call, signs a separate fake callback with the local fake
account's secret, verifies the delivery becomes `delivered`, and
checks precisely one attempt and one receipt. Its existing cleanup
deletes the new fixture's dependent rows.

The previous signed-receipt and concurrency integration had **PASSED**;
this new coordinator section has **NOT YET RUN**. Use the already
migrated, clean `arsamnet_relay_integration_test` schema only.
Do **not** recreate it, rerun migrations, reset tables, or run a
separate queue worker against it.


## Full fake-only coordinator lifecycle executed on Mac

Existing isolated real MySQL 8 script PASS for all baseline checkpoints and the new end-to-end fake dispatch plus separate authenticated fake receipt. Data returned to empty project tables; no new migrations, schema creation, drops, SMTP, SMS or outbound HTTP calls. This does not authorize a real provider send.
