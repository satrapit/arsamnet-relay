# Real MySQL publish integration — isolated and PHP-only

Status: **test source added, not yet run against developer MySQL**. This is
independent of PHPUnit's intentionally forced SQLite \`:memory:\` connection.
It never contacts SMS, SMTP, webhook or any other provider.

## Preconditions and safety

- Developer Mac, APP_ENV=local and uncached Laravel configuration.
- Existing project \`arsamnet_relay\` is untouched.
- Create the exact dedicated schema **arsamnet_relay_integration_test** only.
- The preparation script refuses any existing schema and never drops a database.
- The test runner refuses the main schema, production/staging, non-MySQL-8
  servers, mismatched selected schemas, incorrect queue connection, missing
  migrations, and **any preexisting rows in the 27 project tables**.
- The runner uses only PHP, Laravel's installed dependencies, Symfony Process,
  and MySQL. It executes \`SELECT GET_LOCK\` to guard concurrent test runs.
- PHP unit/feature tests continue to use SQLite in memory as before.
- No \`migrate:fresh\`, \`db:wipe\`, \`rollback\`, TRUNCATE or DROP is part of
  this test workflow. The runner uses fixture-specific DELETE and rolls back
  its first test transaction. If it fails to clean up, stop and inspect
  the isolated schema; **do not run it again against nonempty tables**.

## One-time setup

In a Mac terminal with the repository checked out and \`.env\` pointing at
\`arsamnet_relay\` on the local MySQL server:

\`\`\`bash
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
\`\`\`

If the integration schema already exists, the create script stops without
changing it. If preflight reports any tables, **do not migrate it blindly**.
A successful first migration creates 27 project tables and Laravel's migration
ledger (28 total). The test runner verifies the actual schema metadata
read-only before inserting fixtures.

## Run real transaction/idempotency checks

\`\`\`bash
DB_DATABASE=arsamnet_relay_integration_test \
  php scripts/mysql_publish_integration.php --expect=arsamnet_relay_integration_test
\`\`\`

The runner:

1. Confirms the 28-table/32-FK schema and all project tables empty.
2. Creates encrypted test targets, a client and an API-key record inside a
   transaction, publishes to the real native database queue, rolls back and
   verifies **messages, deliveries and jobs all disappeared**.
3. Publishes once and replays the same key, requiring one message/job.
   Reusing that key with changed payload must fail.
4. Calls \`StageDeliveryJob\` without a provider; verifies local staging only.
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

\`\`\`bash
php artisan test
php vendor/bin/pint --test
php artisan relay:db-verify --expect=arsamnet_relay
git status --short
\`\`\`

The last schema verification deliberately selects the **original main
development schema**, not the test schema. No provider sends or queue
consumption is enabled by this integration test. Report the complete output
of any failed step before making schema changes.
