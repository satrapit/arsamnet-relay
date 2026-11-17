# Security baseline

## Implemented in source, awaiting execution

Fortify login/reset/email verification; disabled public registration; strong CLI initial Super Admin password; mandatory confirmed TOTP for platform routes; recent password confirmation for sensitive writes; Laravel CSRF; security headers; active platform role checking; hashed high-entropy machine keys, revocation, optional CIDR allowlist, IP/key throttling; separate HMAC audit key required for privileged writes.

## Required threat tests

Password spraying and account enumeration, MFA bypass/recovery and session revocation, compromised keys, client IDOR and role.scope mismatch, unsafe webhook SSRF/DNS rebinding, forged callback/replay, duplicated OTP after timeout, audit tampering and stale Cron detection.

Do not log request Authorization headers, OTP, message plaintext, secret adapter credentials, raw webhook bodies or recipient addresses. A per-record HMAC cannot prove rows were not deleted; external anchoring and retention controls require design.

## Missing gates

Login/MFA event aggregation and actionable alerts, robust session revocation UI, provider outbound SSRF validator, callback verification, tested lease/ambiguous state retries, provider-independent operations alerts, cPanel production WAF/TLS review and security penetration tests. No claim of intrusion-proof behavior is valid.

## Current added foundation (source code, runtime tests pending)

Failed-login, MFA-failure and throttling signals are now wired to redacted HMAC-signed security events; the source IP is HMACed, not stored in cleartext. A restricted System > Security monitor page shows event counts over 15 minutes and warns when Cron heartbeats are stale (3-minute threshold). Recovery-code viewing requires recent password confirmation. Login timing for missing/disabled accounts uses a non-secret sentinel hash. The monitor is not a full IDS or external alerting service; production alerts, retention and tamper-evident chaining remain pending.

## IP allowlist threat notes

- CIDR values are validated with `inet_pton`, masked to canonical IPv4/IPv6 network addresses, and matched by address family. Invalid/no-prefix and /0 values fail closed.
- Edits are serialized under a client row lock, capped at 64 rules and recorded as redacted audit events (rule ID only, no raw network range in the audit payload). Route parameters for a rule are scoped to its owning client.
- Removing the final enabled network opens client key access from any source IP; the UI and server require explicit confirmation. The same IP cannot be trusted behind arbitrary proxies; production `trustProxies` must specify controlled proxy ranges only.
- In a normal installation, zero allowlist entries means optional IP controls are off. API keys remain the primary credential. Run negative runtime tests before relying on these restrictions in production.
