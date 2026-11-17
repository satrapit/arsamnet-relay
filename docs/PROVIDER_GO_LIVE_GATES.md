# Provider Go-Live Gates — No Live Sends Authorized

**Status:** DESIGN/REVIEW CHECKLIST, **not an approval to enable providers**.  
**Evidence baseline:** [Current Verification Status](STATUS.md).  
**Target:** Laravel 13, MySQL 8, shared cPanel/CloudLinux; bounded short-lived
Cron workers only. This document applies to SMS, SMTP/email, and outbound
webhook adapters, each with its own verified contract.

## Gate 1 — Select and scope one actual provider

- Record provider name, supported channel, official API/protocol version,
  owned credential scope, sandbox availability, documented SLA/rate limits
  and signed callback/status-query capabilities.
- Separate client-owned and shared accounts. Restrict channel/tenant
  authorization, enabled routes and provider capabilities before any call.
- Define one deterministic initial route. **Never** auto-failover after
  `started`, a timeout, or any ambiguous result. Retry/fallback rules
  require separate provider-specific proof and explicit authorization.
- **No code enables real sending** until the provider contract is chosen
  and reviewed.

## Gate 2 — Credentials and secrets

- Store credentials encrypted with Laravel application encryption, never
  in source control, runtime queue payloads or logs. Document key rotation
  and emergency provider-account revocation.
- Preflight account ownership, scopes, configured endpoint, supported
  channel and enabled status. Do not decrypt credentials for another client.
- Provide redacted configuration errors; do not expose raw provider errors,
  recipient address, OTP, body, access token or SMTP password.

## Gate 3 — Outbound transport boundaries

For **webhooks/HTTP**:
- HTTPS only, strict TLS certificate and hostname verification, bounded
  connect/total timeouts and response body size, no automatic redirects.
- Reject URL userinfo, unsupported ports, unknown schemes, DNS names
  resolving to loopback, RFC1918/private, link-local, multicast,
  documentation/reserved IPv4/IPv6 and cloud metadata endpoints.
- Protect against DNS rebinding by validating *all* A/AAAA answers and
  binding the verified resolved address to the actual connection. Repeat
  checks on every new connection and redirect if redirects are ever allowed.
- Restrict egress destination hostnames to explicit provider-controlled
  allowlists when applicable; no arbitrary client-supplied internal URL.

For **SMTP**:
- Explicit host/port/TLS policy, valid certificate verification, bounded
  connection/request timeouts, dedicated sender identity and no open relay.
- Enforce domain/sender authorization and quota; never downgrade TLS
  silently or log the transport conversation with credentials.

For **SMS**:
- Official provider endpoints, allowed recipient formats and sender IDs,
  message length/encoding controls, explicit timeout and provider quota,
  normalized provider reference and no implicit resend on timeout.

## Gate 4 — Durable attempt and outcome integrity

- Persist `started` attempt before any external call. Use active client-
  scoped fenced lease; reject stale tokens, exhausted quotas, disabled
  provider routes and expired/scheduled messages.
- Distinguish `accepted`, `rejected`, `retryable`, `ambiguous`.
  An HTTP 2xx is **never** end-recipient `delivered`.
- Timeout/connection reset/unknown outcome -> `review_required`;
  never blind retry or failover, particularly for OTP.
- Design operator resolution and audit before enabling retries/secondary
  accounts. Expired leased attempts may already have reached the provider.

## Gate 5 — Verified receipts and replay defense

- Document the chosen provider's **actual** signature scheme or status
  query authentication, not the internal fake HMAC example.
- Authenticate raw-body integrity, identity, timestamp/nonce/event,
  callback account and tenant correlation. Enforce replay/deduplication
  atomically using database unique indexes.
- Require exactly one matching accepted attempt; bind provider reference,
  channel, client and account. Store only sanitized receipt metadata.
- Expose a public callback endpoint only after authentication,
  rate limiting, abuse controls and negative/concurrency test coverage.
- If provider does not offer defensible delivery proof, leave delivery
  in `accepted` (not `delivered`) and document the limitation.

## Gate 6 — Shared hosting and operations

- Queue worker must be bounded by **jobs, execution time and memory**
  using short-lived Cron, not a permanent Supervisor/Redis process.
- Verify no-overlap and lease expiry recovery behavior, after-commit
  dispatch durability and consistent app/queue DB connection.
- Add per-client/provider rate limits and quotas, circuit breakers,
  actionable provider-independent alerts, feature flags default OFF,
  emergency pause and operator audit trail.
- Validate production TLS, firewall/WAF capabilities and actual Cron
  frequency/hosting limits without assuming root/shell access.

## Gate 7 — Evidence required before enablement

| Requirement | Current status |
| --- | --- |
| Fake-only PHP tests and scripted real MySQL integration | **PASS** (see STATUS.md) |
| Provider-specific real transport | **NOT IMPLEMENTED** |
| Provider-specific signed receipt/status proof | **NOT IMPLEMENTED** |
| SSRF-safe webhook implementation | **NOT IMPLEMENTED** |
| Quota/rate/abuse enforcement on real outbound path | **NOT IMPLEMENTED** |
| Bounded Cron and crash-window rehearsal on target hosting | **NOT RUN** |
| Actual provider sandbox negative tests and operator approval | **NOT RUN** |
| Production enablement and release/rollback plan | **NOT APPROVED** |

**Go/no-go:** Do not flip `ARSAM_PUBLISH_ENABLED`, connect provider
workers, create public callback routes, or merge this Draft PR as a
live-sending release until all relevant provider-specific gates have
independent evidence and explicit approval.

No test, migration, schema-reset or provider credential was introduced by
this checklist.


## Kavenegar adapter implementation update — still NO-GO

A concrete Kavenegar transport, provider configuration UI and three machine SMS operations have now been authored, with default-off sending gates and offline HTTP-fake tests. This changes the real-transport line in the prior table from NOT IMPLEMENTED to **SOURCE AUTHORED, NOT VERIFIED**; it does NOT imply provider approval. Critical gaps remain: no real Kavenegar test-account connectivity or charged-send trial, no provider-specific delivery proof/status polling, no host-level outgoing-secret URL log verification, no production quota stress or crash recovery rehearsal. Do not enable global gates or merge the draft PR until developer Mac tests and a separate limited sandbox plan are approved.


## Client-credential configuration guard (new source unverified)

Tenant settings require active owner/admin membership, verified login,
MFA, recent password and the disabled-by-default Super Admin Pennant
`client-provider-overrides` flag. Platform staff have an independent
privileged setup route. These are **source checks pending Mac tests**,
not evidence of production tenant self-service readiness. A dedicated
client portal and membership onboarding are still required for
self-service usability; these routes must be exercised with foreign
client IDs and revoked memberships before approval.
