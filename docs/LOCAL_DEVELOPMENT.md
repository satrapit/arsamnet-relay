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
php artisan key:generate --no-interaction
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
