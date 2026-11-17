# API v1 — implemented endpoints only

GET /api/v1/status: public service/version route, HTTP 200 JSON. Not a full DB/queue/provider health promise.

GET /api/v1/me: requires Authorization: Bearer relay_ followed by 64 lowercase hex digits. A client-issued token is shown once, hashed in MySQL, optionally IP restricted, rate limited, and rejected if revoked/expired/suspended. Returns data.client_id, data.client_slug and data.scopes. Invalid token -> HTTP 401 application/problem+json; no secret is echoed.

Admin web routes (Fortify sessions, not machine API) include /dashboard, /clients, /system/feature-flags and TOTP setup.

Future /api/v1/messages, provider callbacks, topic management, delivery states, OpenAPI 3.1 and Postman/HTTPie imports are NOT implemented. Do not claim accepted/delivered responses until the dispatch pipeline exists.
