# Project context — compact / canonical

**Name:** Arsam Relay • **repo:** satrapit/arsamnet-relay • **branch:** main only • **runtime:** local Laravel 13 / PHP 8.3+ / MySQL 8; intended future cPanel hosting, but staging and deployment are currently out of scope.

**Goal:** secured multi-tenant relay for SMS and later email/webhooks. Avoid needless complexity, duplicate docs/tables and automatic paid provider operations.

**Architecture map:** Fortify platform authentication + confirmed TOTP; users/roles/clients; scoped hashed API keys and optional CIDR allowlist; encrypted Kavenegar account credentials and client routes; Message/Delivery/DeliveryAttempt, idempotent publication, fenced attempt/lease, outcome classification and signed SecurityAudit; Pennant-backed global Settings controlled by Super Admin via AJAX toggles and toast. Database queue supports short-lived workers; no Redis/Supervisor required.

**Implemented SMS:** Kavenegar single / sendarray bulk / OTP verifylookup with strict fixed-host HTTPS, no redirects/retries; provider status inquiry recorded as signed evidence, NOT an automatic delivered transition. `accepted` does not prove handset delivery. Unknown outcomes require manual review. Publishing and real Kavenegar sending default OFF.

**UI:** mobile-first Tailwind 4 + small TailAdmin usage; self-hosted Lucide SVG sprite, no external assets or Font Awesome. System → Settings is grouped, no password reconfirmation (MFA/role/CSRF/audit retained).

**Data:** `docs/ERD.dbml` only (27 tables, 192 fields, 32 refs). The diagram models existing initial migrations; never treat a diagram as migration approval. Existing local `arsamnet_relay` database must not be reset. `arsamnet_relay_integration_test` is a pre-migrated isolated test schema; run its guarded script, do not re-create/drop.

**QA/commands:** `php vendor/bin/pint --test`; `php artisan test`; `npm run build`; `php scripts/validate_design.php`; `php scripts/check_migration_parity.php`; `php scripts/check_migration_relationships.php`; guarded MySQL runner as in README. Historic test results are not claims about later commits. Report passes only after execution.

**Workflow:** direct main changes, no remote CI, no new feature branches, local deployment only. No real paid SMS unless operator explicitly executes a controlled test. Code and docs concise; read graph/context first, then only the necessary files.

**Graphify:** official PyPI package `graphifyy`; CLI `graphify`, Codex project integration `graphify install --project --platform codex`. Install/extract on local Mac and query via `$graphify`. Do not commit `graphify-out/`; after structure changes rebuild locally. Graphify has NOT been run on developer Mac by this assistant.
