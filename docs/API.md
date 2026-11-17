# API v1 — implemented endpoints only

GET /api/v1/status: public service/version route, HTTP 200 JSON. Not a full DB/queue/provider health promise.

GET /api/v1/me: requires Authorization: Bearer relay_ followed by 64 lowercase hex digits. A client-issued token is shown once, hashed in MySQL, optionally IP restricted, rate limited, and rejected if revoked/expired/suspended. Returns data.client_id, data.client_slug and data.scopes. Invalid token -> HTTP 401 application/problem+json; no secret is echoed.

Admin web routes (Fortify sessions, not machine API) include /dashboard, /clients, /system/feature-flags and TOTP setup.

Future /api/v1/messages, provider callbacks, topic management, delivery states, OpenAPI 3.1 and Postman/HTTPie imports are NOT implemented. Do not claim accepted/delivered responses until the dispatch pipeline exists.

## API key expiration and rotation

In the session-authenticated Clients screen, staff with clients.manage permission can issue an API key with 7/30/90/365-day TTL, rotate an existing active key to a new 90-day key, and revoke existing keys. Rotation uses a row lock plus transaction; never reuse an old token. No token appears in audit metadata. Source-only until integrated tests run.

## Machine key source-IP rules

Source-IP allowlists are optional, client-specific restrictions applied to all active machine keys for the client. Any enabled matching CIDR permits the request; an empty enabled list means no source-IP restriction. The admin web interface allows a maximum of 64 normalized IPv4/IPv6 CIDRs (without /0), logs edits, and requires explicit confirmation before deleting the final enabled CIDR. An unauthorized machine request continues to return generic RFC 9457-style HTTP 401 details without disclosing whether key or IP caused denial.

The service must be configured with a carefully limited set of trusted proxies to avoid interpreting spoofed forwarded headers as the source IP.
