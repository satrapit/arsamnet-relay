# Arsam Relay

Multi-tenant messaging relay on **Laravel 13 / PHP 8.3+ / MySQL 8**. Current work happens **locally on `main` only**. No staging host, GitHub Actions, cloud build, automatic deployment or live SMS is required for development.

## Local development

```bash
git switch main
git pull --ff-only origin main
composer install
npm ci
npm run build
php artisan serve
```

Keep local `.env` private. Do not regenerate an existing `APP_KEY` or `ARSAM_AUDIT_HMAC_KEY`: encrypted provider credentials and signed audit records depend on them. The existing MySQL databases are **not disposable**. Never run `migrate:fresh` or an unreviewed migration. First Super Admin is provisioned explicitly using `php artisan relay:provision-admin`; TOTP remains mandatory.

## Architecture

Client-scoped API keys, IP allowlists, tenant-bound provider routes and encrypted Kavenegar credentials; single/bulk/OTP endpoints; idempotent publishing; fenced first dispatch; durable Delivery Attempt; signed Audit and manual review. The `accepted` state is **not delivered**. Unknown/timeouts are quarantined, with no automatic retry or failover. Network sending is off by default and controlled from **System → Settings** (MFA + Super Admin); changing settings does not require password re-entry.

The UI uses Tailwind 4, select TailAdmin components and **bundled Lucide SVG icons** under `public/icons/lucide.svg` (no CDN or icon font).

## Local QA

```bash
php vendor/bin/pint --test
php artisan test
npm run build
php scripts/validate_design.php
php scripts/check_migration_parity.php
php scripts/check_migration_relationships.php
```

For the existing guarded MySQL integration test, use **only the known isolated schema** and its provided runner; never create/drop/re-migrate that schema casually:

```bash
DB_DATABASE=arsamnet_relay_integration_test php scripts/mysql_publish_integration.php --expect=arsamnet_relay_integration_test
```

No GitHub workflow is installed. Builds, test execution and any future deploy are local operator actions. An actual provider SMS is a separate, explicitly enabled and billable operation.

## Canonical project references

- [Compact context and current boundaries](docs/PROJECT_CONTEXT.md)
- [Single dbdiagram.io ERD — DBML](docs/ERD.dbml) (open dbdiagram.io and paste/import as **DBML**, not CSV)
- [Verified status](docs/STATUS.md)
- [Third-party notices](docs/THIRD_PARTY_NOTICES.md)

## Graphify for Codex (local, optional)

Install the verified CLI from the official **`graphifyy`** package on your Mac (for example `uv tool install graphifyy`), then inside the repository:

```bash
graphify --version
graphify install --project --platform codex
graphify extract .
```

Use `$graphify` in Codex to query the project graph before opening a large group of files. Refresh the graph after significant structural changes; graph outputs stay local and ignored. These commands **have not been executed on your Mac** by this assistant. The concise `AGENTS.md` and `docs/PROJECT_CONTEXT.md` are the fallback when Graphify is not installed.

For all source changes: concise PHP-first tests, security boundaries preserved, no GitHub Actions and no feature branches.
