# Arsam Relay — concise agent instructions

Read `docs/PROJECT_CONTEXT.md` first; open specific source files only as required. Graphify (if available) is preferred for dependency maps: query graph before broad scans, refresh after structural edits. Do not pretend its graph is fresh until `graphify extract .` has run locally.

- **Workflow:** develop directly on `main`, local Composer/npm/PHP tests and local deploy only. No GitHub Actions, staging, unsolicited new branches or verbose status docs.
- **Stack:** Laravel 13, PHP 8.3+, MySQL 8, database queue; cPanel-compatible, no always-on worker requirement. Tailwind, TailAdmin components and self-hosted Lucide SVG symbols.
- **Trust boundaries:** Fortify + confirmed TOTP for platform staff, RBAC, tenant ownership, IP/CIDR and key scopes, CSRF, encrypted provider credentials, signed/redacted Audit. Never weaken these with settings flags; settings changes require Super Admin and MFA, not a password reconfirmation.
- **Messaging:** Kavenegar single/bulk/OTP, strict fixed-host TLS, idempotency, durable attempt before call, no auto resend/failover; provider accepted != delivered. Global publishing/send flags off by default.
- **Data safety:** never migrate/reset/drop real or existing integration databases; guarded MySQL runner only. Never put secrets, phone numbers, OTPs or payloads in logs/commits.
- **ERD:** `docs/ERD.dbml` is the **only** dbdiagram.io artifact. Compare migrations with offline PHP checks; do not regenerate CSV samples.
- **QA:** PHP-first tests, Pint, `npm run build` and guarded MySQL integration. If not executed, say NOT RUN. No background workflow.
- **Docs:** keep README, this compact context, single DBML, concise STATUS and license notices. Edit existing docs rather than proliferating files.
- **Localization:** user-facing guidance in Persian; source/docs/UI in clear English.
