# Arsam Relay — project agent instructions

Repository: satrapit/arsamnet-relay. Source requirements: docs/MASTER_PROMPT.md. Read README.md, docs/ARCHITECTURE.md, docs/ERD.md, docs/SECURITY_BASELINE.md and docs/HANDOFF_CODEX.md before changes.

## Rules

- Laravel 13, PHP 8.3+, MySQL 8, shared cPanel hosting; no mandatory Redis, Docker, Node runtime, Supervisor or permanent worker.
- Use modular monolith and native Laravel Database Queue. Domain deliveries are NOT another queue engine.
- The 27-table DBML and draft migrations on the development branch are NOT formally approved. Validate with dbdiagram/MySQL and obtain schema signoff before executing them on non-disposable databases.
- Enforce tenant boundaries, role.scope, key scope, provider ownership, signed callbacks, SSRF protection and redacted logs.
- Fortify, mandatory admin TOTP, recent password confirmation, HTTPS, policies, auditable security events. Never make mandatory protections togglable with Pennant.
- Do not store raw keys, OTP, provider credentials, recipient plaintext or sensitive exceptions in logs/jobs. No public admin registration or default passwords.
- No GitHub Actions, no remote runtime assets, no committed secrets. Use local Font Awesome, Tailwind 4, and TailAdmin Free selectively after compatibility/CSP checks.
- Explain to user in Persian. Source code/UI/docs/comments are English. Keep docs and tests in sync.
- PHP-first QA is required: implement tests and offline schema/ERD/CSV/migration checks in PHP unless a task is technically impossible in PHP. Do not introduce Python scripts for routine tests. Run `php scripts/validate_design.php`, `php scripts/check_migration_parity.php`, `php scripts/check_migration_relationships.php` and `php artisan test`; the offline scripts do not need Composer.
- Run actual commands before reporting PASS: Composer, artisan test, Pint, Vite, Composer audit, migration rollback, security and isolation tests. Otherwise mark NOT RUN.
- Graphify is optional dev tooling; install verified graphifyy, run graphify codex install --project, query and update graph after meaningful structure changes. Ignore generated graphify-out.

## Immediate handoff

Inspect docs/HANDOFF_CODEX.md and docs/VALIDATION.md. Fix blockers and complete Milestone 1 before merger into main.
