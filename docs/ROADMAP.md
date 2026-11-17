# Milestone roadmap

0. Domain/security design, DBML/CSV data dictionary — proposed, owner signoff and official DBML parser pending.
1. Laravel 13 skeleton, Fortify/TOTP, TailAdmin UI foundation, Font Awesome, database queue/Cron, Pennant flags — review branch drafted; package installation, locks, tests and host rehearsal pending.
2. Complete tenant roles/memberships, machine key issuance/rotation/revocation, scoped access and negative tests — initial client/key features drafted only.
3. Publish, subscriptions, transactional outbox, durable message/delivery, ACK/NACK, retry/backoff, idempotency and DLQ — not started.
4. Fake-first SMS/SMTP/webhook adapters, routing, SSRF, callbacks, templates — not started.
5. Full TailAdmin responsive UI, operation alerting, docs/API/Postman/HTTPie, localization — not started.
6. Security/load testing, cPanel rehearsal, backups, release/rollback, license and public review — not started.

Every phase requires explicit test outputs and honest Not Run/Failed/Passed ledger; do not merge or tag an unverified release.


## Current Milestone 3 partial groundwork

Added a **draft, disabled-by-default** direct publish endpoint and ID-only durable queue staging with test cases covering scope/tenant isolation, idempotency replay/conflict, encrypted persistence, no provider calls, and job creation. This is a *partial* Milestone 3 deliverable. User Mac test execution remains pending; real provider adapters, topic fanout, quota enforcement, delivery leases, concurrency guarantees, ACK/NACK, retry/DLQ and production enablement are still NOT IMPLEMENTED. No migrations were added or run for this change.

 
## Milestone 3 — lease groundwork (source only, pending QA)

The initial publish MySQL integration (rollback, one two-process race,
idempotency conflict/replay and no-provider staging) passed on Mac. Next
source changes implement internal tenant-fenced delivery lease claiming,
renewal and expired-uncertainty quarantine, with PHP tests and isolated
MySQL follow-up. They are **not integrated with a sender**, and **not yet
tested on Mac**. No delivery retries, ACK/NACK, DLQ worker, provider routes,
quota controls or production dispatch are claimed.


## Milestone 3 — provider attempt result groundwork

After successful MySQL lease fencing QA, internal (non-sending) durable
`delivery_attempts` insertion and normalized outcome lifecycle were
added with PHP tests. These are **not yet executed on developer Mac**.
Only one attempt is permitted, and uncertain/retryable outcomes require
manual review. This is NOT a complete ACK/NACK interface or a provider
adapter; verified delivery callbacks, safe retry/DLQ and live sending
remain outstanding.


## Milestone 4 preparatory: fake callback authentication

An internal-only `local/testing` fake provider signed-delivery receipt
has been added to exercise HMAC, time skew, event replay, accepted-attempt
correlation, tenant/provider ownership and a transactional delivered
state update. Nine PHP feature tests and an isolated MySQL extension
are pending execution. No public callback endpoint, real adapter or
third-party signature protocol has been implemented; production receipt
processing is **not ready**.


## Full fake-only dispatch lifecycle (source, awaiting QA)

A local/testing-only coordinator connects the existing publish, stage,
lease, durable attempt and verified fake callback primitives. This
rehearses an end-to-end lifecycle without any actual SMS, SMTP or
webhook transport, and without enabling queue consumption. Tests
have been authored but are not yet executed on the developer Mac.
Real provider selection, adapter integration, callbacks, quotas,
automated retries and production release remain separate milestones.
