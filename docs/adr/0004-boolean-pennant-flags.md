# ADR 0004 — Pennant Boolean flags

Status: Proposed / review required
Date: 2026-11-17

Decision: Use first-party Pennant database driver with a fixed allowlist of Boolean optional feature flags. Super Admin writes and Platform Admin read-only; audit modifications. No custom flag engine, remote dependencies, percentage rollouts or client-executable rule language.

Security-critical auth, TOTP, CSRF, policies, tenant isolation, encrypted data and callback signature validation never depend on flags.
