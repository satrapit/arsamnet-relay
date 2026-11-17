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
