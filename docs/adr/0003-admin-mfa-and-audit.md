# ADR 0003 — Mandatory admin TOTP and security audit

Status: Proposed / review required
Date: 2026-11-17

Decision: Fortify session auth with controlled CLI first administrator, no public admin registration. Require verified email, active platform role and confirmed TOTP for all privileged screens; recent password confirmation for writes. Require separate HMAC audit key for sensitive actions and fail closed on audit failure.

Limitations: Application-only IDS is partial; per-record HMAC does not prove row deletion. Recovery/session controls, host security, event sweeps and alerts require further implementation and tests.
