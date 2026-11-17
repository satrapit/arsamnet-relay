# Provider adapters — future implementation contract

SMS, SMTP email and outbound webhooks will use focused channel/capability interfaces. Provider accounts can be platform-shared or owned by one client; encrypted credentials only. Routes are prioritized and must match tenant/provider channel and ownership. Status queries and delivery callbacks are optional declared capabilities.

An ambiguous network timeout must not trigger automatic retry or failover, especially OTP. A provider response of accepted is not delivered. Create safe sanitized attempt records with opaque provider reference IDs; never persist raw credentials, OTP, message body or recipient address in logs.

For outbound webhook SSRF protection, validate scheme, DNS resolution, both IPv4/IPv6 endpoints, private/loopback/link-local addresses, redirects and DNS rebinding with restricted connect timeouts; validate callback signatures/timestamps before state changes.

No real adapters, callbacks or routing policies have been activated in this foundation branch.


## Internal provider-attempt persistence — source draft (2026-11-17)

`DeliveryAttemptManager` is a **non-public, non-sending** service. A future
provider adapter MUST NOT call a network provider until a durable
`delivery_attempts` row exists. Its `begin(clientId, deliveryId, leaseToken,
providerAccountId)` checks:

- Live fenced lease, same tenant, enabled target and active client, unexpired
  message, and zero existing attempts (one-attempt-only in this milestone).
- Explicit enabled `client_provider_routes` matching channel and client.
- Enabled provider account whose `client_id` is either null (shared) or the
  requesting client. Even an accidentally configured cross-tenant route is denied.
- Atomic insertion of a `started` attempt before future network activity;
  the parent delivery's `attempt_count` advances in the same transaction.

`finish(..., ProviderAttemptOutcome, ...)` accepts only the **same active
fencing token** and the one still-`started` attempt. The outcome enum and
delivery state mapping are:

| Internal classification | Attempt outcome | Delivery state | Future action |
| --- | --- | --- | --- |
| Provider accepted | `accepted` | `accepted` | Await separately verified receipt; **not delivered** |
| Definitive rejection | `rejected` | `rejected` | Terminal until new explicit operator policy |
| Safely retryable error | `retryable` | `review_required` | Operator review; **no automatic retry yet** |
| Timeout/unknown result | `ambiguous` | `review_required` | Operator review; never blind resend/failover |

No `delivered` case is accepted by this method. End-user delivery is a
future signed callback/status-verification capability, not a send response.
Normalized `error_code` values contain no provider text, request body,
recipient or OTP. Provider refs must match a strict 1–128 ASCII opaque-ID
pattern and are recorded only for accepted outcomes. Optional HTTP status
must be within 100–599. Free-form provider response metadata is not stored.

`begin` and `finish` are **internal primitives only**, not exposed as
API endpoints or wired to queues. They make no provider calls and do not
create or consume jobs. A crashed worker leaving `started` with an expired
lease is quarantined by the existing lease manager; operator reconciliation
of the outstanding attempt is future scope. Automatic retries, callbacks,
credential decryption, SSRF protection, quotas and provider routing selection
require separate milestones. Until then, **publish remains OFF by default**.
