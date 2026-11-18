# Current project status

- Main-only development, local builds/tests/deployment. GitHub Actions removed; no staging requirement.
- Latest previously developer-confirmed base: Laravel 170 tests / 714 assertions, Pint 108 files, guarded MySQL suite PASS **before later changes**. Subsequent PRs were CI-tested before CI removal; new cleanup/Lucide commits are **NOT YET tested on developer Mac**.
- Arsam Relay includes platform identity/MFA, tenants, API keys, signed audit, Kavenegar single/bulk/OTP via secure adapter, delivery review and grouped Super Admin Settings.
- Publishing, real Kavenegar sending and provider status network flags default OFF. No paid SMS sent by assistant and no live deployment performed.
- Current outstanding QA: run PHP lint/Pint, full PHPUnit, Vite build/npm ci, offline DBML/migration parity checks and guarded MySQL test locally. Open dashboard and Settings on phone and desktop to verify Lucide sprite rendering.
- Diagram: only `docs/ERD.dbml`; dbdiagram.io import **DBML** rather than CSV. Official parser/browser import still needs user confirmation.
- Graphify: compact context and local setup guidance present; actual graph extraction and Codex integration require the user's local machine and are NOT RUN.
