# Arsam Relay — Master Engineering Prompt (Revised, 2026-11-16)

> Paste this entire document into a new ChatGPT conversation to begin Milestone 0, then hand off to Codex. This prompt supersedes prior draft requirements where conflicts exist.

## 1) Mission and engineering roles

به‌عنوان تیمی متشکل از Principal Software Architect، Senior Laravel / PHP Engineer، Senior Full-Stack Engineer، Distributed Systems Engineer، Security Engineer، Database Architect، Senior UI/UX Designer، QA Lead، DevOps Engineer و Documentation Lead عمل کن.

هدف ساخت یک محصول واقعی، سبک، امن، سریع، مستند و قابل‌توسعه برای مدیریت پیام، اعلان و انتقال رویداد میان اپلیکیشن‌ها و مایکروسرویس‌ها است؛ نه بازنویسی کامل RabbitMQ و نه یک پنل صرفاً نمایشی.

اصول غیرقابل مذاکره: امنیت پیش‌فرض، عملکرد، سادگی مهندسی، تست‌پذیری، توسعه‌پذیری، سازگاری با هاست اشتراکی، عدم وابستگی اجرایی به زیرساخت ابری خارجی و مستندسازی مناسب برای توسعه انسانی و AI.

## 2) Official identity and repository

- **Product:** Arsam Relay
- **Project/repository/Composer application slug:** `arsamrelay`
- **Planned production subdomain:** `arsamrelay.arsamnet.com`
- **GitHub owner/repo:** `satrapit/arsamrelay`
- **Repository visibility:** private initially; make public only after a deliberate security/license review.
- **Initial version:** `0.1.0-alpha.1` (SemVer + Conventional Commits + Keep a Changelog).
- **Domain:** Self-hosted Messaging Broker & Notification Management Platform.

Before initializing Git, check that the account is authorized and the repo does not already exist. If repository creation is supported in the environment, create `satrapit/arsamrelay` without adding GitHub Actions workflows; otherwise provide exact `gh repo create` instructions without claiming the repo exists. DNS/subdomain creation is a separate hosting task and must not be claimed completed without verification.

## 3) Fixed technology and hosting constraints

- Laravel **13.x** (check current PHP/DB requirements); PHP **8.3+**, MySQL **8.x/InnoDB**.
- RESTful JSON API `/api/v1`; OpenAPI 3.1; session-based web authentication distinct from machine API credentials.
- Laravel Database Queue, Scheduler, MySQL-backed operational persistence.
- cPanel / CloudLinux shared hosting is the **primary production target**. No mandatory root, Docker, Redis, RabbitMQ, custom systemd, Supervisor, permanent worker, or permanent WebSocket process.
- Cron launches **bounded short-lived PHP CLI workers**, with bounded jobs/time/memory, no overlap hazards, lock lease/recovery logic, retry and dead-letter behavior. Document cron latency, hosting restrictions, potential process limits and failure modes.
- Critical OTP can use a bounded **immediate HTTP dispatch** path (persist first, then attempt within strict timeout); `accepted` is not `delivered`. Ambiguous provider responses must not trigger unsafe retries.
- Node.js can build frontend assets locally; no Node runtime dependency is required on the host. Production uses prebuilt Vite assets.
- All runtime fonts, icons, JS, CSS and other frontend resources must load from the same origin; no required CDN or external runtime service.
- The system itself runs offline from foreign cloud infrastructure; delivery to providers still depends on the chosen SMS/email networks.

## 4) Lightweight modular architecture

Use **Modular Monolith**, Laravel conventions and thin controllers, Application Services, coherent Domain Contracts, Policies and typed DTOs where useful. Avoid microservice decomposition, generic Repository wrappers, needless interfaces, event buses inside event buses, complex plugin loaders and premature abstraction.

Core modules: Identity & Access; Client Management; Messaging; Topics/Subscriptions; Providers/Channels; Templates; Dispatch/Delivery; Administration; Security/Operations; Localization. Keep message, event, notification, queue job, delivery and provider as distinct concepts.

Every extension point must have a focused interface, contract tests and a simple onboarding guide; do not make unrelated concepts share a giant interface.

## 5) Provider and per-client isolation

Support independently configurable adapters for SMS, SMTP/email, webhook and future channels. Providers declare capabilities (send, delivery receipt, status query, callback validation etc.), and only implement capabilities they support.

Support **platform-shared** and **client-owned** provider accounts, encrypted credentials, provider health/config validation, channel-level client routing, explicit priorities and guarded failover. Client A may use its own SMS provider while Client B uses a different one; a client cannot read or select another client's private provider. Protect configurable outbound endpoints against SSRF (scheme/host/IP checks, DNS resolution pitfalls, redirect validation).

Record message attempts, normalized provider responses and provider reference IDs without leaking secret tokens, OTPs or private payloads. Use mock/fake providers for offline tests.

## 6) Messaging consistency and reliability

Design for **at-least-once** delivery, per-recipient/per-subscription delivery state, ACK/NACK, retries with backoff+jitter, expiration, priority classes, DLQ and idempotency. Enforce cross-client access boundaries on every query, worker and webhook.

Use Laravel Database Queue for execution, rather than rewriting it. If a separate durable domain `deliveries` table is justified, clearly specify how Laravel jobs are coordinated with it, what a successful acknowledgment means, and how crashes/lease expiry are handled. Do not accidentally build a second incompatible queue engine.

Document Transactional Outbox integration for producing services. No unconditional provider failover after ambiguous timeout, to prevent duplicate SMS.

## 7) Admin authorization model and identity

Use role-based access control with Laravel Gates/Policies, deny-by-default and explicit client scoping. Suggested roles: Super Admin, Platform Admin, Support, Client Owner, Client Manager, Client Developer, Client Viewer. Distinguish users, memberships, roles, permissions, clients, machine API keys and web sessions. Permissions assigned to Platform roles must be separated from client-scoped roles. Super Admin is not an interchangeable per-client role.

Client settings include activation/suspension, quotas, API key issuance/rotation/revocation, key scopes, expiration, audit, optional IP allowlist, provider assignments and per-client templates. Never store raw machine API tokens, only secure hashes (show once on creation).

Do not add public registration for admin users. Initial super-admin provisioning must be through an explicit controlled secure path and never via seeded default passwords.

## 8) Dashboard and authentication security — mandatory baseline

Absolute 'intrusion-proof' security cannot be guaranteed, especially on shared hosting. Build **defense in depth, detection, auditable response, and measurable hardening**, never claim impenetrability.

Use Laravel Fortify or proven compatible first-party authentication primitives, with TailAdmin-based custom login views. Required controls:

- Strong password hashing, verified emails, secure reset/enrollment flows.
- **Mandatory TOTP MFA for platform administrators**, secure recovery codes, recent-password confirmation for critical security changes; avoid SMS as the sole second factor.
- Rate limits by IP/account/context (database-backed supported cache), bounded progressive penalties and enumeration-resistant responses; ensure shared-IP users are not trivially denied forever.
- CSRF, Secure/HttpOnly/SameSite cookies, HTTPS/HSTS when host supports it, secure session regeneration, session revocation, reasonable idle expiry, validated redirects and CSP/security headers.
- Policies and authorization on every action; strict separation of web sessions and API token authentication; encrypted secrets and short retention.
- **Application-layer intrusion detection**: failed logins/MFA, suspicious reset attempts, repeated 401/403, unusual API-key use, role changes, provider setting changes; normalized severity, safe redaction, compact event retention, tamper-evident audit trail where feasible and actionable admin alerts.
- Scheduled security sweeps via Cron plus inline checks on critical paths; monitor last Cron run. Alerting cannot depend exclusively on the same failed provider.
- Optional host-level WAF/ModSecurity only if available. Do not promise firewall/OS IDS/fail2ban installation without host privileges.
- Risk assessment, threat modeling, automated negative security tests, release audits and a documented incident-response procedure.

**Security-critical controls can never be disabled with Feature Flags**: authentication, MFA for admins, authorization, tenant isolation, CSRF, encryption, audit of privileged actions, token verification, webhook signature validation.

## 9) Feature flags — simple, safe, Super Admin only

Use **Laravel Pennant** with the **database driver** after confirming Laravel 13 compatibility. Do not build a second feature-flag engine.

Provide `Dashboard > System > Feature Flags` (visible/editable only to Super Admin). Show flag key, description, scope (global / client), current state, last change and actor; default-deny undefined flags. Platform admins may see read-only status only if the authorization model explicitly allows it.

For version 1, use straightforward Boolean flags (e.g. webhook-delivery, scheduled-messages, client-provider-overrides); avoid percentage rollout, rule builders, remote flag services and arbitrary executable expressions. Log all changes and provide clear warning/confirmation on high-impact changes. Do not expose flags as a client-controlled API unless explicitly required.

Flag checks must be on relevant backend execution paths, not only UI visibility. Define cache/consistency behavior, scope resolution and a way to test both enabled/disabled states.

## 10) TailAdmin, Tailwind and local icon fonts

Use **TailAdmin Laravel Free** and **Tailwind CSS 4**; integrate UI/components into a clean Laravel 13 application instead of downgrading the framework to the template's Laravel 12 baseline. Use Blade + Alpine.js + Vite, responsive mobile-first accessible UI, English default, dark/light mode only if supported cleanly.

**Required icon-font library:** `@fortawesome/fontawesome-free` (Font Awesome Free, local Web Fonts + CSS). Install via npm and Vite (pin version in package lock); import only necessary CSS families (e.g. core + solid and regular) and package their `webfonts` into local build assets. No Kit, CDN, remote registry, remote fonts or JavaScript service needed at runtime. Maintain licensing/attribution requirements. Use coherent icon wrappers/naming and avoid duplicate icon systems; TailAdmin's built-in SVG icon components may remain where already used, but prefer one standardized UI icon strategy.

## 11) Localization

English UI and documentation initially. Laravel translations, no hard-coded interface strings, locale-aware errors and templates, HTML `dir` based on locale, CSS logical properties and TailAdmin RTL layout compatibility. Prepare for Persian/Arabic without shipping unfinished locale translations.

## 12) Database design comes BEFORE migrations

**Mandatory sequencing:** (1) Domain/entities, (2) DBML, (3) dbdiagram.io ERD review, (4) approved schema/ADR, (5) Laravel migrations, (6) schema drift checks.

Canonical complete-schema source: `docs/ERD.dbml`, compatible with **dbdiagram.io**. Also maintain `docs/ERD.csv` as a machine-readable data dictionary, and `docs/dbdiagram-csv/` with one importable synthetic CSV for each table (dbdiagram.io Import from CSV). CSV does not reliably preserve cross-table relationships, PK/FK, indexes or type fidelity; never claim a single CSV reconstructs the entire relational ERD. Store `docs/ERD.md` describing relationships, constraints and reasons; include an export/snapshot (`docs/ERD.svg` or PNG/PDF where available) and link to dbdiagram project only once actually created. Do not claim to have published a diagram on dbdiagram.io without access to the account.

Use the supplied draft `docs/ERD.dbml` as a starting point, **not an unquestionable fixed schema**. Compare it with actual Laravel, Fortify, Pennant and database queue migrations; reduce unnecessary tables; confirm foreign keys, types, composite indexes, nullable uniqueness and MySQL 8 limitations. Keep DBML synchronized with subsequent schema migrations.

## 13) API contract and client developer experience

REST JSON `/api/v1`, OpenAPI 3.1, correct HTTP status codes, RFC 9457 Problem Details where appropriate, pagination, rate limit documentation, filtering/sorting, request correlation IDs and idempotency, explicit provider delivery states and per-client authorization.

Prepare **working** imports for Postman and HTTPie Desktop, all implemented endpoints, independent example environments, no real keys:

- `resources/api/postman/ArsamRelay.postman_collection.json`
- `resources/api/postman/ArsamRelay.postman_environment.json`
- `resources/api/httpie/ArsamRelay.httpie.json`

Validate actual JSON schemas/formats. If HTTPie native Space creation is not available, use a verified supported import strategy and report any limitation; do not manufacture fake HTTPie schema. Include both positive and negative API tests and sample PHP client integration.

## 14) Engineering and QA discipline

Use automated Unit/Feature/API/Policy/Client-Isolation/Provider Contract/Queue/Concurrency/Security Regression tests, local offline fake providers, migration tests, Laravel Pint, PHPStan/Larastan if version-compatible, Composer Audit and frontend lint where used. Protect PII and OTP from `jobs`, `failed_jobs`, exceptions and logs, not just standard app logs. Avoid unsupported deployment assumptions.

Define performance baselines with measured API response time, memory footprint, DB indexes/queries, enqueue and processing latency under small shared-hosting limits; no invented SLA.

All source code, comments, PHPDoc, database names, UI and documentation in **English**; conversational explanations in **Persian**. Comment why, not what. Keep docs updated with code. Write ADRs for impactful decisions and clear compatibility/rollback/release notes.

Use Semantic Versioning, Conventional Commits, Git tags, CHANGELOG. Start `0.1.0-alpha.1`; no GitHub Actions/workflows without explicit request. No secrets, no hardcoded production addresses, no dependencies on foreign assets at runtime.

## 15) Graphify + Codex handoff

Install Graphify for the project and **Codex project-scoped** integration using the version-verified official CLI, e.g. `graphify codex install --project`; invoke the installed skill in Codex as `$graphify` if that version requires it. Build an initial graph only after meaningful project files exist, query it to reduce redundant file reads, refresh on structural changes. Graphify is dev tooling only, never Production runtime.

Provide root `AGENTS.md` with architecture-first instructions, small change scope, dependency impact checks, test/format gates, docs updates, security review and project-specific Graphify guidance. Provide `docs/HANDOFF_CODEX.md` capturing current milestone, outstanding decisions, commands, tests with Passed/Failed/Not Run, known gaps and next work.

Use short focused chunks, avoid reading the entire project without need, do not invent Graphify commands or claim graph freshness when not rebuilt. Decide what generated Graphify files are committed versus ignored with an explicit rationale.

## 16) Documents and deliverables

At minimum:

```
README.md
CHANGELOG.md
CONTRIBUTING.md
SECURITY.md
AGENTS.md
LICENSE (after license decision)
docs/ARCHITECTURE.md
docs/DOMAIN_MODEL.md
docs/ERD.dbml
docs/ERD.csv
docs/dbdiagram-csv/ (per-table CSV samples)
docs/ERD.md
docs/SECURITY_BASELINE.md
docs/AUTHORIZATION.md
docs/PROVIDERS.md
docs/QUEUE.md
docs/FEATURE_FLAGS.md
docs/API.md
docs/LOCALIZATION.md
docs/TESTING.md
docs/PERFORMANCE.md
docs/SHARED_HOSTING.md
docs/OPERATIONS.md
docs/RELEASES.md
docs/ROADMAP.md
docs/HANDOFF_CODEX.md
docs/adr/
```

Do not create empty placeholder documents merely to satisfy a list; create substantive documents progressively in the relevant milestone.

## 17) Milestones and acceptance criteria

**Milestone 0 — architecture/schema/security**: confirm current dependency compatibility; hosting constraint assessment; domain boundaries; reviewed DBML/dbdiagram ERD; security threat model and critical baseline; minimal RBAC model; provider capabilities; flags policy; initial ADRs; development plan. No migrations until ERD decision.

**Milestone 1 — executable skeleton**: create clean Laravel 13 app, auth/MFA foundation, minimal client model, TailAdmin Free integration and local Font Awesome, MySQL connection, database queue short-lived Cron support, Pennant DB driver with Super Admin-only flags page, local Vite build, translation foundation, unit/feature/security smoke tests, docs and Codex/Graphify setup. Defer advanced send/routing features to later milestones.

**Milestone 2 — identity & clients**: users, platform/client RBAC, client membership, API keys/scopes/rotation, isolation tests.

**Milestone 3 — messaging core**: publishing, topics/subscriptions, durable messages/deliveries, retry/idempotency/DLQ, testable queue semantics.

**Milestone 4 — providers**: per-client/provider routing, SMS/SMTP/webhook adapters, templates, callback validation, failure classification.

**Milestone 5 — API and operations**: polished dashboard, OpenAPI, Postman, HTTPie, alerts/monitoring, localization checks.

**Milestone 6 — production hardening**: threat review, WAF integration if host offers it, cross-tenant and concurrency tests, security and load tests, shared-hosting rehearsal, versioned release and rollback procedures.

Every milestone must list deliverables, acceptance criteria, executed commands and honest **Passed/Failed/Not Run** results. A code file being written does not mean it was tested. Do not promise post-session background work.

## 18) Start now — output contract

1. Verify official versions and dependencies (Laravel 13, TailAdmin Free, Font Awesome Free, Fortify, Pennant, Graphify).
2. State verified assumptions, hosting unknowns and nonnegotiable security limits.
3. Produce technical assessment and architecture/ADR proposals, favoring simplicity.
4. **Review and finalize `docs/ERD.dbml` first** (and maintain per-table importable CSV files and `ERD.csv` catalog), document for dbdiagram.io, and identify application invariants that foreign keys cannot express.
5. Define initial security threat model, login/MFA detection event taxonomy, and a Super Admin-only Pennant feature flag policy.
6. Prepare realistic, executable Milestone 1 code/scaffold if file/terminal access permits; otherwise give exact tested-as-possible commands, files and explicit Not Run status.
7. Prepare `AGENTS.md`, Graphify setup plan, concise Codex handoff and next milestone tasks.
8. If GitHub repo creation is permitted by available tools and authorization, create `satrapit/arsamrelay` (private) and report actual URL; otherwise output safe `gh` instructions and do not pretend it was created.

**Language:** Discuss and explain in Persian. Write code, source text, migrations, UI, API and engineering documentation in English.

**Begin with Milestone 0. Do not merely brainstorm; produce concrete, reviewable project artifacts.**