# Milestone roadmap

0. Domain/security design, DBML/CSV data dictionary — proposed, owner signoff and official DBML parser pending.
1. Laravel 13 skeleton, Fortify/TOTP, TailAdmin UI foundation, Font Awesome, database queue/Cron, Pennant flags — review branch drafted; package installation, locks, tests and host rehearsal pending.
2. Complete tenant roles/memberships, machine key issuance/rotation/revocation, scoped access and negative tests — initial client/key features drafted only.
3. Publish, subscriptions, transactional outbox, durable message/delivery, ACK/NACK, retry/backoff, idempotency and DLQ — not started.
4. Fake-first SMS/SMTP/webhook adapters, routing, SSRF, callbacks, templates — not started.
5. Full TailAdmin responsive UI, operation alerting, docs/API/Postman/HTTPie, localization — not started.
6. Security/load testing, cPanel rehearsal, backups, release/rollback, license and public review — not started.

Every phase requires explicit test outputs and honest Not Run/Failed/Passed ledger; do not merge or tag an unverified release.
