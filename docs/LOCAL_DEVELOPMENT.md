# Local development and first dependency installation

Status: development instructions, not a production deployment checklist.

## What the first installation output means

With PHP 8.3+, Composer 2.9+, Node 24+ and npm 11+, a successful `composer install`, `npm install`, `npm run build` and `php artisan route:list` verify dependency resolution, frontend compilation and Laravel route registration. They do **not** prove database migrations, permission enforcement, message delivery or a working scheduler.

When there is no `.env` at the project root, Laravel falls back to defaults, which can report **APP_ENV=production** and **DB_CONNECTION=sqlite**. Running `php artisan schedule:list` under those defaults currently tries to read the `cache_locks` table because scheduled tasks use `withoutOverlapping` and database cache. The resulting error that `database/database.sqlite` is missing does **not** by itself indicate a scheduler defect.

Avoid creating a SQLite database to hide that error: the supported deployment target is MySQL 8 and the schema has composite foreign keys that still require a real MySQL rehearsal.

## Local-only setup (no migration)

From the local checkout and development branch:

```bash
git pull --ff-only origin feat/laravel13-foundation
test -f .env || cp .env.example .env
grep -Eq '^APP_KEY=.+php artisan config:clear
php artisan about
CACHE_STORE=array php artisan schedule:list
```

The copy step preserves an existing `.env`, and APP_KEY is generated only if the local file has no key; do not rotate an existing key, because doing so invalidates previously encrypted values. Confirm APP_ENV=local, APP_DEBUG=true, DB_CONNECTION=mysql and DB_* values point **only** to a disposable development MySQL 8 database before proceeding to database operations. Replace DB_USERNAME/DB_PASSWORD locally; never print/upload the file or commit it to Git. Generate ARSAM_AUDIT_HMAC_KEY locally with `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` and copy the result into the private `.env`. It must have at least 32 bytes of entropy.

The inline `CACHE_STORE=array` overrides the database cache for the **schedule listing command only** so it can list scheduled entries without running migrations or requiring a database. Production keeps `CACHE_STORE=database` and requires a migrated cache and cache_locks table for overlapping-task protection.

Do not start `schedule:run` or the queue worker until the MySQL schema and Cron strategy are reviewed and tested.

## Dependencies and reproducibility

The first Composer installation generates `composer.lock` and npm generates `package-lock.json` **locally**. These are required for repeatable application deployments and must be version-controlled once reviewed. Use:

```bash
composer validate --strict
composer dump-autoload --optimize
npm run build
git status --short
```

Composer PSR-4 warnings about offline stub classes were resolved by relocating standalone tests to `scripts/offline/`; regenerate the autoloader **after pulling that change**.

`composer.json` currently declares `proprietary` only because no final license was approved. The GitHub repository is publicly visible; this declaration does not grant an open-source license. Decide an appropriate license before any public release.

The Vite fontaine/optimized-font-fallback message is informational and the build still succeeds. The optional package is not needed for correct icon or font rendering.

## Testing boundaries

After pulling, run the non-DB checks:

```bash
php scripts/offline/AuditIntegritySmoke.php
php scripts/offline/IpCidrSmoke.php
python3 scripts/check_migration_parity.py
php artisan route:list
CACHE_STORE=array php artisan schedule:list
```

For PHPUnit, `phpunit.xml` **forces** APP_ENV=testing, DB_CONNECTION=sqlite and DB_DATABASE=:memory: (and clears DB_URL), so PHPUnit's configured database must be a disposable in-memory SQLite database, not local/production MySQL. This safety measure is only meaningful if config is **not cached**, and if you run the project test suite with the supplied phpunit.xml. Do not pass custom `--configuration` paths or override these settings. Ensure the sqlite PDO driver is available and clear config caches first:

```bash
php artisan config:clear
php -r 'echo implode(", ", PDO::getAvailableDrivers()), PHP_EOL;'
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

If SQLite fails due to driver or schema differences, preserve the failure and report it. A separate disposable MySQL 8 integration test environment is required before approving the DBML/migrations. **Do not run `php artisan migrate`, `migrate:fresh` or `migrate:refresh` against an unknown database or a shared environment.**

Git commit guidance: review `composer.lock` / `package-lock.json` changes and, after validation, commit *only* those generated lockfiles (not `.env`, `vendor/`, `node_modules/`, or `public/build/`) to the feature branch, then push. Record exact lockfile contents rather than recreating versions from a log.
 .env || php artisan key:generate --no-interaction
php artisan config:clear
php artisan about
CACHE_STORE=array php artisan schedule:list
```

The copy step preserves an existing `.env`. Confirm APP_ENV=local, APP_DEBUG=true, DB_CONNECTION=mysql and DB_* values point **only** to a disposable development MySQL 8 database before proceeding to database operations. Replace DB_USERNAME/DB_PASSWORD locally; never print/upload the file or commit it to Git. Generate ARSAM_AUDIT_HMAC_KEY locally with `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` and copy the result into the private `.env`. It must have at least 32 bytes of entropy.

The inline `CACHE_STORE=array` overrides the database cache for the **schedule listing command only** so it can list scheduled entries without running migrations or requiring a database. Production keeps `CACHE_STORE=database` and requires a migrated cache and cache_locks table for overlapping-task protection.

Do not start `schedule:run` or the queue worker until the MySQL schema and Cron strategy are reviewed and tested.

## Dependencies and reproducibility

The first Composer installation generates `composer.lock` and npm generates `package-lock.json` **locally**. These are required for repeatable application deployments and must be version-controlled once reviewed. Use:

```bash
composer validate --strict
composer dump-autoload --optimize
npm run build
git status --short
```

Composer PSR-4 warnings about offline stub classes were resolved by relocating standalone tests to `scripts/offline/`; regenerate the autoloader **after pulling that change**.

`composer.json` currently declares `proprietary` only because no final license was approved. The GitHub repository is publicly visible; this declaration does not grant an open-source license. Decide an appropriate license before any public release.

The Vite fontaine/optimized-font-fallback message is informational and the build still succeeds. The optional package is not needed for correct icon or font rendering.

## Testing boundaries

After pulling, run the non-DB checks:

```bash
php scripts/offline/AuditIntegritySmoke.php
php scripts/offline/IpCidrSmoke.php
python3 scripts/check_migration_parity.py
php artisan route:list
CACHE_STORE=array php artisan schedule:list
```

For PHPUnit, `phpunit.xml` **forces** APP_ENV=testing, DB_CONNECTION=sqlite and DB_DATABASE=:memory: (and clears DB_URL), so PHPUnit's configured database must be a disposable in-memory SQLite database, not local/production MySQL. This safety measure is only meaningful if config is **not cached**, and if you run the project test suite with the supplied phpunit.xml. Do not pass custom `--configuration` paths or override these settings. Ensure the sqlite PDO driver is available and clear config caches first:

```bash
php artisan config:clear
php -r 'echo implode(", ", PDO::getAvailableDrivers()), PHP_EOL;'
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

If SQLite fails due to driver or schema differences, preserve the failure and report it. A separate disposable MySQL 8 integration test environment is required before approving the DBML/migrations. **Do not run `php artisan migrate`, `migrate:fresh` or `migrate:refresh` against an unknown database or a shared environment.**

Git commit guidance: review `composer.lock` / `package-lock.json` changes and, after validation, commit *only* those generated lockfiles (not `.env`, `vendor/`, `node_modules/`, or `public/build/`) to the feature branch, then push. Record exact lockfile contents rather than recreating versions from a log.


## MySQL 1049 when checking a freshly created database

MySQL error `SQLSTATE[HY000] [1049] Unknown database 'arsamnet_relay'` means the current MySQL server did not find that schema at the supplied host/port. It is **not** proof of a bad migration, nor is it the error for a missing migrations table. A database may have been created under a different name, on a different MySQL instance, or with a hosting account prefix.

Use read-only checks, and do not display DB_PASSWORD or APP_KEY:

```bash
php artisan config:clear
grep -E '^(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE)=' .env
mysql -h 127.0.0.1 -P 3306 -u YOUR_DB_USERNAME -p -e "SHOW DATABASES;"
```

Replace `YOUR_DB_USERNAME` from your private `.env` locally. The MySQL CLI prompts for the password; **do not** put the password in the shell command. Check the database name and host/port used to create it. If the client is unavailable, inspect the same MySQL instance using the local database manager. Change only local `.env` to match the *existing* schema and run `php artisan config:clear` before retrying `php artisan db:show` and `php artisan migrate:status`. These are inspection commands; an absent migrations table is expected prior to migration. Do not run `migrate` until the confirmed target is empty, disposable and approved.

## First PHPUnit result and generated assets

On the developer Mac, `php artisan test --testsuite=Unit --stop-on-failure` passed **2 tests / 8 assertions**. `--testsuite=Feature --stop-on-failure` had **7 passing tests, one failing stock ExampleTest, and 18 not run**; key issue/rotation and IP allowlist tests were among the passes. The stock example incorrectly expected HTTP 200 at `/`, while this project intentionally redirects to `/dashboard` (302). That expectation is fixed in the source branch; after pulling, run the entire Feature suite **without `--stop-on-failure`** to expose further failures:

```bash
php artisan config:clear
php artisan test --testsuite=Feature
```

`composer audit --locked` reported no known advisories for the then-installed locks; it does not establish that every dependency or application is vulnerability-free. A Vite build deletes `public/build/.gitignore` because it replaces the entire build directory; the nested placeholder has now been removed from version control since the root `.gitignore` already ignores `/public/build/`.


## Initial migration rehearsal — exact-schema preflight

The developer has confirmed local PHP Unit/Feature suites pass (2+26 tests, 90 assertions), and MySQL 8.0.33 connects to the corrected `arsamnet_relay` schema. `db:show` may list hundreds of tables belonging to **other schemas** visible to the `root` user; do not treat the server-wide total as tables in the current schema.

The project now includes a read-only guard designed for the **first migration** of a dedicated, empty, disposable local development database. Pull the latest feature branch and run:

```bash
php artisan config:clear
php artisan test
php artisan relay:db-preflight --expect=arsamnet_relay
```

This command uses the actual selected MySQL connection to validate all of: APP_ENV=local; configuration is not cached; driver=MySQL; MySQL 8.x server, not MariaDB; exact schema name matches `--expect`; and count of tables and views in **that schema only** equals zero. It performs **no writes** and does not print credentials.

If preflight PASS and this database is exclusively for the project and can be discarded, you may test the initial migrations using `php artisan migrate --database=mysql`, then `php artisan migrate:status`. Migrations are MySQL DDL and a failing migration may have already created some tables: **STOP on any error; do not use `migrate:fresh`/reset/drop on this or another schema unless verified disposable**. Do not use the root MySQL user in production: create a dedicated least-privilege application DB user before deployment.

The preflight only protects **the check at the time it runs**; always ensure no one edits the .env/selected connection between preflight and migration. Migration status being missing does not by itself imply the database is empty.

The default `DatabaseSeeder` no longer inserts a predictable sample account. Real platform administrators must be provisioned via the explicit protected CLI. Composer `setup` no longer generates a replacement APP_KEY if the existing .env already has one.

 
## After the first successful MySQL migration

The first user-reported migration (2026-11-17) ran **all four project migrations successfully**, after read-only preflight confirmed an empty local MySQL 8 schema. This is now a **populated database**, so `relay:db-preflight` must refuse it; do not run that preflight expecting PASS again.

Check the current schema without modifying it:

```bash
php artisan config:clear
php artisan test
php artisan relay:db-verify --expect=arsamnet_relay
php artisan migrate:status
```

`relay:db-verify` reads the local `docs/ERD.dbml` and checks the selected MySQL 8 schema using `information_schema` only (plus read-only migration history). It compares documented field names/types/declared string lengths/required columns, unique and ordinary indexes and foreign keys, including composite tenant references. Missing or unexpected schema elements produce failure descriptions. Its parser is deliberately limited to the DBML constructs used by this project, **not** an official DBML validation library.

The verifier cannot prove safe delivery, tenant boundary enforcement under real concurrent writes, default values, DDL rollback safety or race-free migrations. MySQL `ALTER`/`CREATE` DDL may commit independently; on any future migration failure inspect state before making changes. For MySQL transactional/runtime behavior, use a **separate disposable test database**, never the current schema for `migrate:fresh` or rollback.

The initial full PHPUnit result: **31 passed (96 assertions)**. The new schema-parser and verifier tests introduced after that result have not yet been run.

 
## Follow-up after metadata verification (2026-11-17)

The first `relay:db-verify` run found two relationships already enforced by MySQL but missing in DBML. Both are now documented (32 in total). **Do not re-run migrations** to fix a documentation mismatch.

```bash
git pull --ff-only origin feat/laravel13-foundation
php artisan config:clear
python3 scripts/validate_design.py
python3 scripts/check_migration_parity.py
python3 scripts/check_migration_relationships.py
php artisan test
php artisan relay:db-verify --expect=arsamnet_relay
```

On the Mac, Pint's non-modifying check reported 20 formatting issues. To fix formatting automatically and check again:

```bash
./vendor/bin/pint
./vendor/bin/pint --test
php artisan test
composer validate --strict
```

Pint changes the formatting of tracked PHP files; review the diff before committing. The already-generated local `composer.lock` and `package-lock.json` also need review and commit for reproducible builds. Do **not** stage `.env`, vendor or node_modules.

If the database verifier still reports FK discrepancies, preserve the full output and stop before changing any MySQL constraints. The verifier is read-only and cannot repair a mismatch.
