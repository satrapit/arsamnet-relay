# API v1 — implemented endpoints only

GET /api/v1/status: public service/version route, HTTP 200 JSON. Not a full DB/queue/provider health promise.

GET /api/v1/me: requires Authorization: Bearer relay_ followed by 64 lowercase hex digits. A client-issued token is shown once, hashed in MySQL, optionally IP restricted, rate limited, and rejected if revoked/expired/suspended. Returns data.client_id, data.client_slug and data.scopes. Invalid token -> HTTP 401 application/problem+json; no secret is echoed.

Admin web routes (Fortify sessions, not machine API) include /dashboard, /clients, /system/feature-flags and TOTP setup.

Provider callbacks, topic management, delivery-state queries, OpenAPI 3.1, and Postman/HTTPie imports are NOT implemented. A **new opt-in** durable *direct* message publisher is drafted, but its runtime tests on Mac have not yet run. It performs no external delivery.

## API key expiration and rotation

In the session-authenticated Clients screen, staff with clients.manage permission can issue an API key with 7/30/90/365-day TTL, rotate an existing active key to a new 90-day key, and revoke existing keys. Rotation uses a row lock plus transaction; never reuse an old token. No token appears in audit metadata. Source-only until integrated tests run.

## Machine key source-IP rules

Source-IP allowlists are optional, client-specific restrictions applied to all active machine keys for the client. Any enabled matching CIDR permits the request; an empty enabled list means no source-IP restriction. The admin web interface allows a maximum of 64 normalized IPv4/IPv6 CIDRs (without /0), logs edits, and requires explicit confirmation before deleting the final enabled CIDR. An unauthorized machine request continues to return generic RFC 9457-style HTTP 401 details without disclosing whether key or IP caused denial.

The service must be configured with a carefully limited set of trusted proxies to avoid interpreting spoofed forwarded headers as the source IP.


## Draft POST /api/v1/messages — safe direct publishing (2026-11-17)

**OFF BY DEFAULT.** Set `ARSAM_PUBLISH_ENABLED=true` in a private development `.env` and run `php artisan config:clear` only when intentionally testing. Do not enable on production until separate MySQL integration/concurrency, quota, rate limits, provider support, monitoring and rollback gates pass.

Requires an active machine bearer token with the `messages.publish` scope (and its existing per-client IP allowlist), plus an `Idempotency-Key` header containing 16–128 ASCII letters, digits, dots, hyphens or underscores. Existing `throttle:relay-machine` applies. Payload must be JSON:

```json
{
  "target_ids": [123, 124],
  "payload": {"event": "example", "reference": "opaque-id"},
  "priority": "normal",
  "ttl_seconds": 3600
}
```

The 1–25 existing, enabled target IDs **must belong to the authenticated client**. The payload must be a JSON object; the complete request is limited to 16 KiB. Priority is `low`, `normal` or `high` (default normal), and TTL is 60–86400 seconds (default 3600). Neither raw recipient addresses nor plaintext provider credentials are accepted. Scheduled/topic fanout publishing is *not* implemented.

A new request returns HTTP 202 with `data.message_id`, `data.delivery_ids`, `data.state = "stored"` and `data.replayed = false`. A byte/semantic equivalent request using the same idempotency key returns HTTP 200 with the same message ID and `replayed = true`. The same key with different content/targets/priority/TTL returns 409, a missing/invalid key or target validation error returns 422, insufficient key scope returns 403, and publishing disabled returns 503. Sensitive payload is stored with Laravel Crypt encryption; fingerprints and idempotency keys are HMACed using the application key, never returned.

This endpoint **accepts and stores a message; it does NOT mean an SMS, email, or webhook has been accepted by any provider**. The native database queue records contain only delivery IDs. `StageDeliveryJob` either marks a queued delivery `awaiting_provider` (no provider calls) or `expired`. No actual `delivery_attempts` are created yet. Future milestones will integrate provider routes, leasing, ACK/NACK, safe retries and DLQ. Until then this is a controlled development-only foundation, not a general-use messaging service.
