# Arsam Relay — agent engineering contract

Read `README.md`, `docs/ARCHITECTURE.md`, `docs/ERD.md`, `docs/SECURITY_BASELINE.md`, `docs/QUEUE.md` and the current `docs/HANDOFF_CODEX.md` before changing significant code. Follow Laravel 13/PHP 8.3+/MySQL 8 and cPanel constraints. Human explanations in Persian; source, tests, documentation, comments, UI and database names in English.

## Guardrails

- **Milestone 0 status: proposed. Do not create Laravel migrations until `docs/ERD.dbml` is explicitly approved.** Do not claim the external dbdiagram project exists.
- Prefer one modular Laravel application, thin controllers, small services and policy-scoped Eloquent queries. Do not invent a generic repository layer or duplicate Laravel Database Queue.
- Every data access, webhook and queued job must prove the client boundary; validate role.scope, provider-account ownership and selected template/API-key ownership.
- Never store tokens, OTP bodies, provider credentials, plaintext destinations or sensitive exceptions in logs, queues, failed jobs, analytics, exceptions or test fixtures. Do not seed admin credentials.
- Use Fortify + mandatory admin TOTP and policies; never disable security controls via Pennant. Keep machine API keys separate from web sessions.
- Use TailAdmin Laravel Free as a component source, not as a reason to downgrade Laravel. Font Awesome Free must be bundled from local npm build. No runtime CDNs or external assets.
- No permanent workers/systemd/Supervisor/Redis assumptions. Use bounded database jobs + Cron, documented recovery and `ambiguous` send state to prevent OTP duplicates.
- Prefer focused changes and explicitly describe the scope. Document why decisions are made, not literal code behavior. Add/update ADRs for architectural decisions and synchronize ERD and migrations after approval.
- No `.github/workflows` or GitHub Actions without user instruction. No secrets or production credentials in Git, hardcoded production paths or Node build requirements on host.

## Quality gate on every change

Run the smallest relevant tests first, then full `php artisan test`, `vendor/bin/pint --test`, PHPStan/Larastan if installed/compatible, Composer audit, `npm run build` and documented host-specific checks as applicable. Mark every command explicitly **PASSED / FAILED / NOT RUN**, never imply execution. Run negative isolation/security/tenant tests on any authorization, queue, provider, routing or flags change. Avoid modifying generated lock files manually.

## Graphify + Codex integration

Official source `https://github.com/Graphify-Labs/graphify`; official package `graphifyy`. After useful source files exist and CLI is installed, from repo root run `graphify --version`, `graphify codex install --project` and use `$graphify` in Codex to build/query graph. Check CLI help before assuming option syntax; graph freshness must be verified after structural code changes. Commit project-scoped skill instructions only after reviewing generated content and licenses; **ignore `graphify-out/` default machine-generated graph/artifacts** unless an explicit artifact policy is approved. Don't graph an empty repository.

## Current task

Review pending ERD/ADRs; check toolchain and actual host limits; on schema approval begin Milestone 1 with installable framework and security skeleton. Update `docs/HANDOFF_CODEX.md` at end of each session with facts and results.