# Changelog

All notable changes to Arsam Relay will follow Keep a Changelog and Semantic Versioning.

## Unreleased

### Added
- Official Laravel 13 application foundation with MySQL/database queue assumptions.
- Fortify login/reset/email verification, mandatory platform TOTP gate and controlled CLI first admin provisioning.
- Draft 27-table database schema and dbdiagram DBML/CSV artifacts.
- Platform clients, one-time hashed API keys, revocation and scoped machine endpoint.
- Pennant global flags with Super Admin authorization and redacted audit records.
- Responsive Tailwind / local Font Awesome UI foundation; one TailAdmin Free card component and accessible mobile drawer.
- Authentication failure/MFA throttling event capture with redacted IP HMAC, restricted security monitor and audit integrity check command.
- Recent password confirmation before recovery-code disclosure; dummy hash verification for absent-account login timing.
- Dependency-free audit integrity smoke assertions and negative-security test sources.

### Pending
- Composer/npm dependency lockfiles, MySQL and Laravel test execution.
- Actual TailAdmin Free components, provider adapters and safe delivery pipeline.
- Formal schema approval, cPanel deployment rehearsal and version tag.

No version has been released.

### Additional foundation changes (unreleased)

- Added expiring API keys with configurable TTL and transactional key rotation, including a cross-client negative regression test. Not validated against live MySQL.

- Added normalized IPv4/IPv6 client source-IP allowlists, guarded admin CRUD, explicit last-rule opt-out, tenant scoping, audit event tags and 23 passing offline matcher checks.
- Added an offline schema/table/column parity guard and fixed the key-expiration view's Carbon namespace. Runtime migrations and HTTP tests still pending.

- Confirmed macOS Laravel test run: 26 Feature tests (82 assertions) + 2 Unit tests (8 assertions) all pass on in-memory SQLite; MySQL DDL still untested.
- Removed predictable default sample account from DatabaseSeeder; introduced read-only MySQL target/emptiness preflight for the first local migration; adjusted Composer setup to avoid rotating existing APP_KEY.

- First real local MySQL 8 migration completed: four initial migrations applied in batch 1 after zero-table preflight; 31 PHPUnit tests / 96 assertions now pass on SQLite :memory:.
- Added a read-only DBML-to-MySQL schema verifier checking field types/lengths, required flags, 30 restricted foreign-key relationships and declared indexes. Added four unexecuted safety/parser tests. No schema reset or rollback executed.

- Verified 35 Mac Laravel tests / 108 assertions. Reconciled two existing subscriptions/deliveries owner FKs missing from the ERD; documented 32 total relationships, no MySQL DDL changes.
- Added a strict offline migration-to-ERD foreign-key parity checker. Awaiting new MySQL schema-verifier result and Pint autofix (20 reported style issues).

- Replaced three Python QA tools with dependency-free PHP implementations and five PHPUnit cases; all future schema/design validation uses PHP as the default. No DB migrations or runtime changes.

- Fixed PHP-only FK validator handling of `foreign('delivery_id')->references('id')` alongside composite arrays, after Mac feedback; added three regression tests. MySQL schema unchanged. Pint rerun pending.

- Drafted opt-in `POST /api/v1/messages` with encrypted transactional persistence, idempotency conflict detection, tenant-scoped targets and native database queue staging jobs containing only opaque IDs. No provider sending; off by default. Nine feature tests added (unexecuted on Mac).

- First Mac runtime verification of the safe direct-publish stage: 9 feature tests/48 assertions PASS; all 52 Laravel tests/166 assertions PASS. MySQL metadata inspection PASS. Fixed new-file Pint formatting complaints (follow-up Mac run pending).

- Developer Mac confirmed 52/52 PHP tests (166 assertions), Pint 72/72 files, strict Composer validation and read-only MySQL metadata verifier all PASS after formatting. Local formatter diffs and two generated lockfiles remain uncommitted; concurrency/production tests still pending.

- User committed/pushed the two genuine formatting/lockfile commits; Composer
  and npm vulnerability audits reported zero advisories. Added safe PHP-only
  MySQL integration schema setup and rollback/concurrent idempotency test
  harness, not yet executed on the developer Mac.

- Developer Mac confirmed the isolated MySQL 8 integration schema and four
  migrations passed. Real MySQL transactional rollback, sequential/replayed
  idempotency, one two-process race and fixture cleanup all PASS. Added
  provider-free tenant-scoped delivery leases, token fencing, expiry-capped
  renewal and uncertain-lease quarantine to operator review, with ten PHP
  Feature cases and expanded MySQL checks (new cases pending Mac run).

- Expanded PHP MySQL integration now PASS for delivery lease claim, fenced
  renewal and ambiguous-expiry quarantine in addition to rollback and
  two-process idempotency; developer pushed one-file Pint correction
  `ea6b919`, and Git worktree is clean.

- Introduced internal, inert provider-attempt bookkeeping: tenant-scoped
  routed `started` attempts, normalized accepted/rejected/retryable/
  ambiguous outcomes, strict fencing and sanitized status/ref values.
  Added ten PHP Feature cases and new fake-only MySQL integration checks.
  Actual execution on Mac remains pending; no provider adapter or sends.

- Developer Mac verified internal provider-attempt accounting: ten new PHP feature tests/50 assertions, full suite 72/257, Pint 79 files and isolated real MySQL transaction/attempt/fencing/race checks all PASS. No provider calls. One local integration-test Pint formatting change awaits commit and push.

- Added fake-only, local/test-only HMAC-signed callback receipt verification,
  unique event-HMAC replay protection, strict provider/tenant/attempt
  correlation and atomic accepted-to-delivered state update. Nine new
  PHP Feature cases and expanded isolated MySQL assertions were authored
  (Mac execution pending). No real provider callback route or send.

- Verified full fake signed callback stage on developer Mac: 81 PHP tests and 292 assertions PASS; isolated real MySQL receipt verification, replay rejection and cleanup PASS; primary MySQL schema verification and clean Git worktree confirmed. No public callback endpoint or real provider sending.

- Added an internal local/testing-only fake dispatch coordinator that
  composes staged delivery, fenced lease, durable attempt and
  simulated provider acceptance; a separately signed fake receipt
  remains required for delivered state. Seven PHP Feature cases and
  isolated MySQL full-lifecycle checks added; Mac execution pending.
  No real adapter or network sends.

- Mac confirmed 88 PHP tests/331 assertions, Pint 83 files and isolated MySQL full fake-only message lifecycle PASS; production/main schema read-only verifier PASS and local Git clean. No real provider sends.

- Introduced a typed provider transport interface, capability declarations,
  transient/redacted/nonserializable outbound request, normalized result,
  and fake-only local/testing adapter with nine PHP contract tests
  (not yet executed on Mac). No real transports, worker connection or sends.

- Mac validated provider contract (9 tests/37 assertions), full 97 PHP
  tests/368 assertions, Pint 89 files, and isolated MySQL fake lifecycle.
  Then connected the internal local-only fake coordinator to the new
  typed provider adapter and added three encrypted-data/payload negative
  PHP tests (new implementation pending Mac QA). No sends or migrations.

- Mac verified adapter-wired fake coordinator: 10 focused tests/51 assertions,
  full PHP suite 100 tests/380 assertions, Pint 89 files and standalone
  real MySQL integration PASS. No provider sends or migrations.

- Mac confirmed 100 full Laravel PHP tests/380 assertions and 89-file
  Pint PASS; isolated real MySQL adapter-wired fake dispatch, lease,
  Attempt, callback, concurrency and cleanup PASS; original DB metadata
  and Git clean. Added a read-only, tenant-bound initial provider route
  selector with nine PHP tests and a guarded MySQL check (not yet run).

- Added and consolidated a single read-only, tenant-scoped provider route
  selector for unattempted staged deliveries, honoring ascending priority,
  active/owned/shared routes, message expiry and schedule, and production
  exclusion for fake-only accounts. Eleven PHP tests and a real MySQL
  checkpoint await Mac QA. No automatic failover or real sending.

- Developer Mac verified read-only ProviderRouteSelector with eleven focused tests/25 assertions, full 111 PHP tests/405 assertions, Pint 91 files, standalone MySQL route+fake lifecycle checks and clean Git. Future scheduling and fake-in-production guards included.

- Verified Mac QA for initial ProviderRouteSelector: 111 PHP tests/405 assertions, Pint 91 files and isolated MySQL PASS. Added local/testing-only first-eligible fake dispatch integration, schedule guard for explicit fake calls, five new PHP tests and a MySQL schedule/priority checkpoint (new tests NOT YET RUN). No send or migration.

- Added gated Kavenegar SMS single/sendarray/VerifyLookup transport, client encrypted account/route management UI, authenticated machine SMS APIs and sanitized status, durable attempt reporting and rate/cooldown guards; added 24 PHP tests and real-MySQL HTTP-fake scenarios. All new QA still NOT RUN on Mac; no live SMS or schema changes.

- Kavenegar single/bulk/VerifyLookup transport, scoped machine API,
  encrypted provider settings and offline HTTP-faked unit/feature/MySQL
  scenarios authored; added opt-in tenant-owned configuration paths
  guarded by client owner/admin membership, MFA, password confirmation
  and Pennant feature flag. Total 28 new PHP tests authored, not run
  on Mac. Live sending remains disabled, Draft PR unchanged.
