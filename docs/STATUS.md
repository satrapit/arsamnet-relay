# Current project status

- Main-only development, local builds/tests/deployment. GitHub Actions and staging-only preflight removed; no staging requirement.
- Latest previously developer-confirmed base: Laravel 170 tests / 714 assertions, Pint 108 files, guarded MySQL suite PASS **before later changes**. Subsequent PRs were CI-tested before CI removal; new cleanup/Lucide commits are **NOT YET tested on developer Mac**.
- Arsam Relay includes platform identity/MFA, tenants, API keys, signed audit, Kavenegar single/bulk/OTP via secure adapter, delivery review and grouped Super Admin Settings.
- Publishing, real Kavenegar sending and provider status network flags default OFF. No paid SMS sent by assistant and no live deployment performed.
- Current outstanding QA: run PHP lint/Pint, full PHPUnit, Vite build/npm ci, offline DBML/migration parity checks and guarded MySQL test locally. Open dashboard and Settings on phone and desktop to verify Lucide sprite rendering.
- Diagram: only `docs/ERD.dbml` (27 tables / 192 fields / 32 references); import/paste as **DBML** in dbdiagram.io, not CSV. Braces, brackets and relation count statically checked; official parser/browser import still needs user confirmation.
- Graphify: compact context and local setup guidance present; actual graph extraction and Codex integration require the user's local machine and are NOT RUN.

- Lucide: one self-hosted 20-symbol SVG sprite; Font Awesome removed from npm, CSS and main views. Local smoke script accepts only `http://127.0.0.1:<port>` / `localhost` for the operator-triggered, potentially billable test; repeated attempts are blocked by a local marker. Neither a browser check nor a live SMS has been performed.
- Remote housekeeping: five already-merged legacy feature branches still require deletion by the repository owner; GitHub connector has no delete-branch action.

- Latest local Kavenegar work (NOT YET Mac-tested): single/two-number bulk/OTP manual script with local machine scope and client readiness preflight, private one-time markers, sanitized response; `--status` reads only persisted delivery information. Added tenant-scoped `GET /api/v1/sms/readiness` plus feature tests for flags, route scope and quotas. No provider network operation performed by assistant.
