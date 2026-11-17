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
