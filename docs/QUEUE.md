# Native database queue and bounded Cron

Laravel Database Queue is the execution mechanism. Messages, deliveries and delivery attempts track domain facts; they are not a parallel queue engine. Jobs carry only opaque IDs.

Scheduler registers heartbeat and bounded queue:work. Worker parameters: high,default,low queues; stop when empty; at most 25 jobs or 45 seconds; 96 MiB cap; guarded by database overlap lock. cPanel Cron runs php artisan schedule:run once each minute. Validate host PHP CLI, process limits, cache locks and PCNTL behavior.

Future dispatch flow: persist message/deliveries atomically; claim a fenced delivery lease; enforce tenant, expiry, status and provider routing; create attempt before external call; classify accepted, delivered, retryable, rejected, ambiguous. Use exponential backoff with jitter only on safe retries. Never retransmit ambiguous OTP; send to operator review. Implement DLQ, job crash recovery and idempotency tests before live sends.

There is no active delivery job, message publishing route or DLQ processor in the current branch. The Cron schedule itself is not tested on cPanel.


## Draft transactional publish staging — no actual sends

The opt-in `POST /api/v1/messages` implementation creates an encrypted `messages` record, per-client `deliveries` records and native `jobs` queue records **inside one Laravel DB transaction**. It requires the database queue to use the same default connection; an explicitly different `DB_QUEUE_CONNECTION` is rejected rather than silently breaking atomicity. The job's serialized application data is only an opaque delivery ID. A 202 response represents durable local storage, not provider delivery or receipt.

The `StageDeliveryJob` is deliberately inert with respect to providers: `queued → awaiting_provider`, or `queued → expired`. No provider account is contacted, no delivery attempt is generated, and no retries/failover/acknowledgements are claimed. Any future processing loop must recognize and re-drive `awaiting_provider` deliveries after the provider pipeline exists, with proper lease/fencing and poison message handling. Publish enablement defaults to false.

 
## Real MySQL staging transaction verification (unexecuted)

Two new PHP-only integration scripts independently check nested transaction
rollback of message+delivery+native database job records and competing
idempotent submissions from two PHP processes. They require an empty,
separately migrated `arsamnet_relay_integration_test` schema and cannot run
against the ordinary `arsamnet_relay` development database.
Read `docs/MYSQL_INTEGRATION.md`. No provider calls, no sender/consumer
loop and no destructive DB reset have been added.

 
## Lease/fencing foundation — no provider processing

An internal `DeliveryLeaseManager` provides a narrow state machine for
future workers; it is not connected to a public endpoint or automatic sender:

- `awaiting_provider -> leased` via an atomic, tenant-bound claim; checks
  live client, enabled target, nonexpired message and a clean lease field set.
- A lease token is a new random UUID; repeated claims are rejected. Renewals
  require the matching token, live lease, active ownership and unexpired
  message. The deadline cannot exceed message expiry.
- Expired `leased` rows can only be quarantined to `review_required`
  with `LEASE_EXPIRED_UNCERTAIN`. **Do not automatically reclaim, retry or
  fail over** an uncertain attempt, especially an OTP.
- `review_required`, `expired`, `leased` and other non-staged states
  are not claimable. No state transition marks a provider receipt delivered
  or inserts a delivery attempt.

The local PHP/SQLite feature tests and expanded isolated MySQL lease cases
were added after the last Mac regression; those additions are **NOT YET RUN**.
Claim is purely an internal primitive and must not be wired into a real
provider worker without routing, status, attempt durability, crash recovery,
quotas and audit design.


## Attempt durability and conservative outcome policy (source draft)

A separate internal `DeliveryAttemptManager` can persist a unique
`delivery_attempts` record with normalized `started` outcome under an
active fenced lease and an explicitly tenant-authorized provider route.
This transaction increments `deliveries.attempt_count` exactly once.
The milestone intentionally **does not allow a second attempt**, even on a
retryable error; real retry scheduling and crash recovery are not ready.

`ProviderAttemptOutcome` supports accepted, rejected, retryable and
ambiguous. Accepted is **not delivered**; rejected is terminal; retryable
and ambiguous lead to `review_required`, never automatic retransmission.
Result recording uses the same lease token and updates attempt+delivery
atomically; repeat/stale result writes are denied. The service does no
provider I/O and no background job calls it. A true provider dispatch
coordinator and ACK/callback validation remain unimplemented.

The PHP-only regression and expanded isolated MySQL tests have been
written but **NOT RUN YET** on developer Mac.


## Fake-only end-to-end dispatch rehearsal (new source, unverified)

The internal `FakeDispatchCoordinator` joins publish/stage with the
already implemented lease/attempt/fake-receipt primitives. It is a
**manual local/testing-only entrypoint**, not added to Cron, the native
queue worker, a public API, or any outbound client. It requires the
explicit fake provider and route, persists one attempt under a fenced
lease, and simulates `accepted` but not `delivered`. Verified fake
HMAC receipt remains a separate step. No automatic retry is added.

Seven PHP Feature cases and additional isolated real MySQL integration
assertions have been written, but have **NOT YET BEEN RUN ON MAC**.
