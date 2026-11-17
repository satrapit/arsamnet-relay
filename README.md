# Arsam Relay

Self-hosted, multi-client messaging and notification platform for Laravel 13, PHP 8.3+ and MySQL 8, designed for cPanel shared hosting.

**Development foundation only — NOT production ready.** No real SMS/email/webhook sending is enabled.

## Repository

- https://github.com/satrapit/arsamnet-relay (**currently PUBLIC**).
- Product: Arsam Relay. Repository: arsamnet-relay.
- Planned hostname: arsamrelay.arsamnet.com (**not deployed or DNS-verified**).
- Target version: 0.1.0-alpha.1 (**no tag published**).
- Original requirements: docs/MASTER_PROMPT.md. Its older proposed repository address is superseded by this user's actual GitHub URL.
- Canonical proposed schema: docs/ERD.dbml; 27 tables, 192 columns, 30 relationships. Formal schema signoff is pending.
- License decision and public security audit are pending. Never commit production secrets.

## Code now present (not runtime-tested)

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

**Only after ERD review and a disposable MySQL migration rehearsal:** run php artisan migrate. The draft migrations in this branch have not been applied or verified.

For first Super Admin, temporarily enable ARSAM_PROVISION_ADMIN=true, clear any cached configuration and run:

    php artisan relay:provision-admin admin@example.org --name="Platform Administrator"

The command prompts for the password securely. Disable provisioning immediately and enroll TOTP at first sign-in. No public administrator registration.

## Runtime / deployment notes

Only public/ may be the web document root. Build public/build assets off-host; no Node runtime is required by cPanel. To use Scheduler, configure a verified every-minute Cron invoking php artisan schedule:run. No Redis/Supervisor/systemd/permanent process dependency.

Dependency installation and Vite build succeeded on the developer Mac (Composer 133 packages, npm 102 packages). Unit tests passed (2), and 7 Feature tests passed before a stock example test failed because it expected HTTP 200 from the intentionally redirected root URL; the test expectation is fixed in the feature branch but awaits rerun. Database migrations have not been executed: the current configured MySQL name returned error 1049. The package lockfiles exist locally only, pending review/commit. See docs/VALIDATION.md and docs/LOCAL_DEVELOPMENT.md.

No GitHub Actions workflows were created.

## Isolated smoke tests

Run `php scripts/offline/AuditIntegritySmoke.php` without Composer for the narrow Audit HMAC/redaction test. Run `php artisan relay:verify-audit --limit=1000` after installing dependencies and migrating the approved schema; it detects edits to rows under review but not deletions. See docs/VALIDATION.md for exact test evidence.

## Machine API key lifecycle

Platform admins can issue keys expiring after 7/30/90/365 days, revoke keys, or rotate keys. Rotation replaces the token in one database transaction (the old token is revoked immediately, and the replacement is shown once); the new token expires after 90 days. Operations require recent password confirmation and log a redacted HMAC-tagged audit event. Initial in-memory SQLite Feature tests for key issuance/rotation/tenant isolation PASSED; MySQL integration/concurrency tests remain NOT RUN.

## Client machine IP allowlist

Platform administrators can manage optional source-IP CIDR rules at **Clients → IP allowlist**. Rules are normalized (IPv4/IPv6), limited to 64 per client, require a recent password confirmation to edit and are audited without exposing raw network ranges in the audit payload. **An empty allowlist means no IP restriction**; removal of the final enabled rule requires typing `UNRESTRICT`. Never assume this replaces API-key authentication.

Verification without Composer: `php scripts/offline/IpCidrSmoke.php` (23 assertions passed). For reproducible ERD/migration **name** comparison: `python3 scripts/check_migration_parity.py`. Run the real Laravel Feature suite and disposable MySQL checks before deploying.

## Local development

For first installation, `.env` creation, database-free Scheduler verification and test/lockfile rules, follow [docs/LOCAL_DEVELOPMENT.md](docs/LOCAL_DEVELOPMENT.md). Do not run migrations on an unverified database.
