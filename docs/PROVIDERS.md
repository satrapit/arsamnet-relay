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


## Internal fake-only signed callback receipt (NOT a real provider adapter)

`FakeSignedCallbackReceipt` is deliberately callable only inside
`local/testing`, with **no HTTP endpoint**. It does not accept an
untrusted "verified" flag or caller-supplied secret: it looks up an enabled
`fake-only` provider account, decrypts a JSON `callback_secret`
(length >= 32 bytes), and authenticates `sha256=<64 lowercase hex>` against
HMAC-SHA256 over the exact UTF-8 string:

```text
<provider-account-id>\n<10-digit-UNIX-time>\n<raw-JSON-body>
```

The signature is checked in constant time, after strict time/size limits
(300-second skew, 4 KiB raw body). The signed JSON object has exactly
`event_id`, `provider_ref` and `status`; only a valid
`status="delivered"` is accepted by this fake contract. The ref must
resolve unambiguously to an `accepted` attempt for the same provider.
The delivery must still be `accepted` and belong to an active client
with an enabled matching provider route and channel, and no cross-tenant
private-provider ownership mismatch.

Persist only an HMAC of the event ID, opaque attempt relation and
normalized status/timestamps in `callback_receipts`. The existing
provider+event-HMAC unique index prevents duplicate recording; the
delivery's row lock and state transition deny a second event.
Both writes are atomic. No raw body/signature, provider secret, recipient
or message text is persisted in callback_receipts.

**Never reuse this fake signature protocol as a third-party provider
protocol.** Each real provider requires its own documented signature/
timestamp/replay and callback capability, URL/authentication/security
review, delivery proof mapping and network tests before a public route
can be considered. The new fake-only PHP tests and isolated MySQL
runner extension have not yet been executed.


## Local fake-only dispatch coordination — internal and unverified

`FakeDispatchCoordinator` composes the existing stages without a real
provider adapter or network transport. It is **local/testing-only**,
is **never registered as a queue worker**, and has no HTTP route. A single
manual internal call is allowed for an already staged delivery only when:

- The client is active and owns that delivery/target.
- An enabled `fake-only` provider account matches the channel, belongs
  to the same client (or is platform-shared), and has an enabled matching
  route.
- Its encrypted JSON credentials contain a fake `callback_secret` of
  at least 32 bytes.

It takes a fenced lease, durably records the first and only attempt, and
immediately simulates `accepted` with a random opaque `fake-...`
provider reference. It **never marks delivered**, performs network I/O,
handles external credentials, or schedules retries. The separate local fake
HMAC receipt service must authenticate a correctly signed `delivered`
event before the delivery becomes `delivered`.

Failure after obtaining a lease conservatively leaves the lease/attempt
for later expiry review rather than silently resetting it to sendable.
This is a test seam, **not a production provider or dispatch engine**.
No PHP/real MySQL tests of the new coordinator have run on the developer Mac.


## Transport-neutral provider adapter contract — source only, QA pending

The new `App\Contracts\Providers\OutboundProviderAdapter` is a narrow,
typed boundary for future SMS, SMTP/email and outbound webhook transports.
Each implementation declares `code()`, `channel()`,
`capabilities()` and `send(OutboundProviderRequest): OutboundProviderResult`.
Capabilities are explicit `Send`, `SignedDeliveryReceipt` and
`StatusQuery`; merely declaring a send capability does **not** authorize
marking a delivery as `delivered`.

`OutboundProviderRequest` exists only in short-lived process memory, with
private destination/content values, size/channel validation and redacted
debugging. It deliberately throws on PHP serialization, so it cannot be
silently embedded in Laravel's native queue payloads. Consumers must not
log request objects, recipient identifiers, OTPs, credentials or plaintext
content. This is defense-in-depth, **not** a substitute for application
redaction and PII controls.

`OutboundProviderResult` is deliberately limited to normalized
`ProviderAttemptOutcome`, optional HTTP status (100–599) and optional
validated opaque provider reference. It never stores raw provider responses.
`accepted` remains distinct from `delivered`; retryable and ambiguous
results remain review-only until safe retry policies exist.

The sole implementation is `FakeOutboundProviderAdapter`, usable only in
`local/testing` and explicitly supporting SMS, email or webhook *as fake
channels* without real network operations. It returns an opaque synthetic
`fake-ULID` and does not access the database, send emails/SMS or call a
URL. It is **not wired** into the existing fake coordinator, public APIs,
production queue, runtime provider routing, or any real transport.
Its capability declaration is for fake signed receipts only and does not
implement a third-party callback verification protocol.

Nine PHP `ProviderAdapterContractTest` cases are added for capabilities,
channel isolation, synthetic accepted-not-delivered result, local-only
guard, redacted debug output and serialization protection, invalid request
inputs and normalized response metadata. **New code has NOT run on Mac**.
Real adapters, selected providers, SSRF-safe webhook transports and
callback authentication remain future implementation phases.


## Fake coordinator wired to the adapter contract (source draft)

`FakeDispatchCoordinator` now creates the transient
`OutboundProviderRequest` using the tenant-owned delivery's encrypted
target address and message payload. It validates and decrypts these only
inside local/testing, after checking the active client, enabled target,
fake provider and enabled same-channel route. Invalid ciphertext, bad
channel or excessive plaintext is rejected **before** leasing and attempt
creation.

After taking a fenced lease and persisting one started attempt, the
coordinator calls the no-network `FakeOutboundProviderAdapter::send`
and applies only the normalized `OutboundProviderResult`. Simulated
`accepted` remains **not delivered**; a separately authenticated fake
signed callback is required. If the adapter throws or returns accepted
without a provider reference, the attempt is conservatively treated as
ambiguous rather than blindly requeued.

This is an intentionally scoped fake-only transport integration. It does
not register the fake adapter as a global provider, add a worker, decrypt
real provider credentials for sending, or implement real SMS, SMTP and
webhook networking. Code is **awaiting new Mac PHP/MySQL regression**.


## First-attempt provider route selection (source draft, QA pending)

`ProviderRouteSelector::select(clientId, deliveryId): ?int` chooses the
lowest numeric priority **eligible** provider account for a new staged
delivery without decrypting credentials or sending. It reads the existing
`client_provider_routes`, `provider_accounts`, `delivery_targets`,
`clients`, `messages` and `deliveries` tables. It requires active
client, enabled target and enabled same-channel route/account, correct
private account ownership (or a platform-shared account), message not
expired, delivery state `awaiting_provider`, zero attempts, no lease.
Any mismatch returns null. The unique priority constraint makes the
configuration order deterministic.

This is **initial routing only, never retry/failover**. Once a delivery
has been leased, attempted, accepted, rejected, quarantined or delivered,
the selector will not select any account. It does not create a job,
lease or Attempt and does not use the decrypted credentials. It is
not yet called by the fake or production dispatch coordinator; the
existing tested fake path keeps its explicit provider account argument.
Nine feature tests plus an extra MySQL-only selection checkpoint have
been authored but **NOT YET RUN on Mac**.
