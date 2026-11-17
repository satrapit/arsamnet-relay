# Codex handoff — Arsam Relay

## Context / current milestone

Project: `arsamrelay`, intended GitHub `satrapit/arsamrelay` (private, **not created**), intended domain `arsamrelay.arsamnet.com` (**not verified**). Milestone **0 — architecture/schema/security** is **partially delivered** as docs. No Laravel application exists in this handoff. `docs/ERD.dbml` is proposed and **not migration-approved**. Baseline is 27 tables, 192 columns, 30 refs; CSV artifacts are not substitutes for DBML.

## Dependency and environment verification

External sources checked 2026-11-17: Laravel 13 PHP >=8.3; Fortify v1.42.0 and Pennant v1.27.0 support Illuminate 13; TailAdmin Laravel Free README currently targets Laravel 12 + Tailwind 4; Font Awesome Free v7.3.1; Graphify official `graphifyy`/`graphify codex install --project`. Sandbox has PHP 8.4.24, Node 22.16 and git; **Composer, gh, Graphify, DBML CLI not available** here. This is not the target host.

## Immediate work in exact order

1. Confirm owner approval of `docs/ERD.dbml`, ADR-0001..0004 and scope/retention decisions. Validate DBML in official DBML parser/dbdiagram.io, assess composite FK/index viability on MySQL 8, compare Laravel 13/Fortify/Pennant migration stubs, and record schema sign-off. **No migrations until then.**
2. From the actual developer laptop, verify `php -v`, `composer --version`, `node -v`, `npm -v`, `gh auth status`; from host, run `bash scripts/host-preflight.sh` and verify Cron/permissions/manual SMTP egress safely.
3. Verify `satrapit/arsamrelay` does not exist (`gh repo view satrapit/arsamrelay`). If 404 and account authorized, execute `gh repo create satrapit/arsamrelay --private --description "Self-hosted messaging and notification management platform"`. Confirm with `gh repo view satrapit/arsamrelay --json name,visibility,url`. Do not mark done until verified. No GitHub Actions.
4. **After schema approval**, start a new clean Laravel 13 application (`composer create-project laravel/laravel arsamrelay "^13.0"`); keep the design pack documents and verify composer package compatibility with `composer require laravel/fortify:^1.42 laravel/pennant:^1.27`, resolving versions within installed constraints. Do not assume command execution just because documented.
5. Install official Fortify assets/config following 13-compatible docs. Implement controlled CLI Super Admin provisioning, email verification, password reset, mandatory TOTP before privileged pages and session/request security. Write authentication/Policy negative tests before dashboard polish.
6. Import selected TailAdmin Laravel Free Blade/Alpine/Tailwind 4 components (no Laravel downgrade), `npm install --save-exact @fortawesome/fontawesome-free@7.3.1`, import local CSS and use Vite font assets; test prebuilt build works without network at runtime. Use compatible lockfiles.
7. Configure database session, database cache+locks, database queue and bounded scheduled worker; implement minimal client/role model. Before dispatching real messages validate database queue semantics and Cron heartbeat. Install Pennant database driver, Super Admin-only UI, tests for both flag states.
8. Optional later: `uv tool install graphifyy` or `pipx install graphifyy`; from root: `graphify --version`, `graphify codex install --project`. Ask Codex to use `$graphify`, build graph after meaningful code, and refresh it after structural changes. Check emitted files before committing; `graphify-out/` remains ignored.
9. Do not implement SMS, SMTP or webhook sending before Milestone 3/4; focus on functional secure scaffold for Milestone 1.

## Suggested quality commands (NOT RUN in the design pack)

```bash
python3 scripts/validate_design.py
php -v
composer --version
composer validate
php artisan about
php artisan migrate:fresh --env=testing
php artisan test
./vendor/bin/pint --test
composer audit
npm ci
npm run build
php artisan route:list
php artisan schedule:list
```

Run only applicable commands when files and dependencies exist. `migrate:fresh` **must** use a disposable test database after schema approval; never run on production or developer data.

## Verification ledger (2026-11-17)

| Check | Result | Evidence/limitation |
| --- | --- | --- |
| Confirm framework and package compatibility from official sources | PASSED (metadata only) | Links in README |
| GitHub authenticated account | PASSED | Connector reports `satrapit` |
| Existing target repository accessible | NOT FOUND | GitHub 404; could be absent or inaccessible; do not infer public accessibility |
| Create private repository | NOT RUN | GitHub connector has no create-repository action; `gh` unavailable |
| Generated DBML reference consistency, CSV format | PASSED | `python3 scripts/validate_design.py` when executed |
| Official dbdiagram.io ERD parse / public project | NOT RUN | No authorized dbdiagram session or CLI |
| Actual MySQL migrations / rollback | NOT RUN | No approved schema and no app/DB |
| Laravel test suite, API, auth and queue | NOT RUN | No app or Composer dependencies |
| Host, DNS, TLS, provider egress, Cron constraints | NOT RUN | Actual hosting not accessed |
| Graphify install / graph query | NOT RUN | CLI not present and no meaningful app code |

## Open decisions

- Schema sign-off and 27-table surface; database native checks / FK index layout; message/attempt retention and encryption key rotation.
- Which adapter/provider to implement first; timeout thresholds; exact OTP latency budget; how to surface `ambiguous` delivery to operators.
- Actual cPanel cron/process and CLI capabilities, PHP `pcntl`, MySQL locking behavior, provider outbound connectivity.
- License terms and any open-source publication review; no LICENSE file before decision.