# Contributing

Use focused feature branches, Conventional Commits, precise docs and negative security tests. No GitHub Actions workflows are allowed without owner request. Do not check in .env, production keys, vendor dependencies or node_modules. Resolve dependencies and update lockfiles using tools rather than editing locks by hand.

Use PHP as the default test/validation language, including ERD, CSV, and migration checks. Do not add Python QA dependencies without a documented technical need. Run the three `scripts/*.php` design validators, `php artisan test`, and `php vendor/bin/pint --test`. Node/npm are retained for Vite/Tailwind builds.

Before requesting merge, run Composer validation/audit, PHP tests, Pint, a Vite build, disposable MySQL migration/rollback, authorization/tenant checks and check cPanel assumptions. State failed or not-run checks explicitly. No public release until security and licensing review.
