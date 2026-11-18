# Kavenegar SMS provider — integration guide

**Status (2026-11-17):** Implementation and offline HTTP-fake tests committed on development branch. **NOT YET RUN on the developer Mac.** No real SMS, provider account verification or production deployment occurred.

Official API references:
- https://www.postman.com/kavenegarrestapi/kavenegar-s-public-workspace/documentation/v5ppwag/kavenegar-rest-api
- https://github.com/kavenegar/kavenegar-php/blob/master/src/KavenegarApi.php

## Supported operations

| Relay endpoint | Kavenegar endpoint (HTTPS POST) | Scope |
| --- | --- | --- |
| POST /api/v1/sms/send | /v1/{API-KEY}/sms/send.json | One receptor and message |
| POST /api/v1/sms/bulk | /v1/{API-KEY}/sms/sendarray.json | 1–25 distinct receptors; one provider HTTP request |
| POST /api/v1/sms/otp | /v1/{API-KEY}/verify/lookup.json | One receptor, approved template and tokens |
| GET /api/v1/sms/messages/{message_ulid} | Internal database | Sanitized delivery/attempt history, no outbound provider call |

The Kavenegar host is fixed to https://api.kavenegar.com. Arbitrary URLs, redirects and client-selected hosts are forbidden. Form-encoded HTTPS POST matches the official PHP SDK. TLS verification is ON. Connection timeout: 3s; total timeout: 8s; no automatic HTTP retry.

## Configure per-client provider credentials

In the Arsam Relay staff dashboard, open Clients → SMS provider for the selected client. Staff must have clients.manage permission, confirmed MFA and recent password confirmation. Enter your Kavenegar API key, registered sender, route priority and enable the account/route. The key and sender are encrypted at rest in provider_accounts, and the raw key is never displayed again.

The account is scoped to one client (or a platform-shared account can be configured separately). A tenant cannot read another client's account. Tenant self-service credential management is not in this first version; privileged platform staff configure the provider.

**Both global gates default OFF**; account activation never overrides them:

~~~ini
ARSAM_PUBLISH_ENABLED=false
ARSAM_KAVENEGAR_SEND_ENABLED=false
ARSAM_KAVENEGAR_HOURLY_LIMIT=100
~~~

Do NOT enable real sending until independent PHP/MySQL tests, Kavenegar sandbox validation, operator approval and production readiness review are complete. Never place a real Kavenegar API key in source control or test fixtures. The Kavenegar official API puts its key in the URL path: suppress outbound URI, HTTP trace, request exceptions and proxy/APM logs containing that URL.

## Client machine API

All endpoints require HTTPS, the existing tenant-bound Relay machine bearer token, matching optional IP allowlist, rate limits and the Idempotency-Key header (16–128 safe ASCII characters) for sending. Send scope: messages.publish. Read-only status scope: messages.read.

### One SMS

~~~http
POST /api/v1/sms/send
Authorization: Bearer relay_REPLACE_WITH_REAL_CLIENT_TOKEN
Idempotency-Key: demo-single-20261117-0001
Content-Type: application/json

{"receptor":"09123456789","message":"Example SMS"}
~~~

### Bulk SMS (sendarray)

~~~http
POST /api/v1/sms/bulk
Authorization: Bearer relay_REPLACE_WITH_REAL_CLIENT_TOKEN
Idempotency-Key: demo-bulk-20261117-0002
Content-Type: application/json

{
  "items": [
    {"receptor":"09123456789","message":"Hello A"},
    {"receptor":"09123456780","message":"Hello B"}
  ]
}
~~~

Supports 1–25 different mobile recipients; one separate text per recipient. The overall request is limited to 16 KiB. Numbers in 09xxxxxxxxx, +989xxxxxxxxx, or 989xxxxxxxxx are normalized to 09xxxxxxxxx. The adapter initially supports Iranian mobile numbers only. For larger volumes submit independently idempotent batches rather than bypassing the 25-item limit.

Each delivery gets a durable started attempt before the single sendarray HTTP request. Results must contain exactly as many unique provider IDs as submitted items; an incomplete/uncertain response quarantines all affected attempts instead of blindly resending or falling back.

### OTP through VerifyLookup

~~~http
POST /api/v1/sms/otp
Authorization: Bearer relay_REPLACE_WITH_REAL_CLIENT_TOKEN
Idempotency-Key: demo-otp-20261117-0003
Content-Type: application/json

{"receptor":"09123456789","template":"LoginCode","token":"123456"}
~~~

Optional token2 and token3 are supported. The named pattern must already exist and be approved in the Kavenegar account. **The calling app generates, validates and expires OTPs; Relay only sends the supplied token.** Tokens and recipient addresses are encrypted at rest and never returned or written to queue payloads, logs or status output. One fresh OTP per client/recipient/minute; message TTL is 5 minutes, but that does not validate or revoke a token in the originating app.

## Outcomes, logging and query

New submissions return 202 with message_id, delivery_ids, normalized state, provider reference(s) when available and replayed=false. A matching Idempotency-Key replay returns HTTP 200 with replayed=true and does not call Kavenegar twice. A changed replay returns 409.

- accepted: accepted by Kavenegar with a valid provider message ID, **not delivered to phone**.
- rejected: explicit provider-side validation rejection; no automatic retry.
- review_required: ambiguous/timeout/incomplete result; no retry or failover.
- unavailable: message persisted but no safe first-attempt dispatch could start; needs operator reconciliation.
- mixed: bulk result contains different individual delivery states; inspect the per-delivery array.
- already_recorded: replay; never a second send.

GET /api/v1/sms/messages/{message_ulid} uses messages.read and returns only owned message IDs, delivery states, attempt_count, HTTP status, normalized error code and safe provider reference. The staff SMS provider screen lists the latest 30 attempts. No phone number, OTP, plaintext message, API key or raw Kavenegar response is shown.

Response codes: 401 invalid machine token/IP, 403 missing scope, 409 key conflict, 413 oversized input, 422 invalid/duplicate number, disabled account or client limit, 503 globally disabled sending. Rate limits: 120/minute/IP and 60/minute/machine token, plus default 100 SMS deliveries/client/rolling hour and an OTP cooldown.

## Data flow and current limitation

Authenticated API → encrypted tenant target/message → atomic message/delivery/ID-only native Laravel database job → bounded synchronous stage → read-only first route → fenced lease → durable attempt(s) → one Kavenegar HTTP request → sanitized result/status. No provider secret or OTP is serialized into jobs. Only a verified provider-specific callback or status query may establish delivered status; this is **not implemented for Kavenegar yet**, so successful sends remain accepted.

A crash between durable publication and dispatch may leave an unsent queued/staged delivery. Idempotent API replays will never force a resend. This safety-first design requires an operator recovery workflow before fully unattended production messaging. Automatic retries and second-provider failover remain disabled.

## Required Mac evaluation (offline: Http::fake)

~~~bash
cd ~/Sites/arsamnet-relay
git pull --ff-only origin feat/laravel13-foundation
php artisan config:clear

php -l app/Providers/Adapters/KavenegarSmsAdapter.php
php -l app/Services/KavenegarMessageService.php
php -l app/Services/KavenegarSmsDispatcher.php
php -l app/Http/Controllers/Api/KavenegarSmsController.php
php -l app/Http/Controllers/Admin/KavenegarAccountController.php
php -l tests/Unit/KavenegarSmsAdapterTest.php
php -l tests/Feature/KavenegarSmsApiTest.php
php -l tests/Feature/KavenegarAccountConfigurationTest.php
php -l scripts/mysql_publish_integration.php

php vendor/bin/pint
php vendor/bin/pint --test
php artisan test --filter=Kavenegar
php artisan test

DB_DATABASE=arsamnet_relay_integration_test \
php scripts/mysql_publish_integration.php \
--expect=arsamnet_relay_integration_test

php artisan relay:db-verify --expect=arsamnet_relay
git diff --check
git status --short
~~~

All new automated HTTP interactions are faked, even in MySQL integration. The MySQL script enforces the exact existing separately migrated, initially empty arsamnet_relay_integration_test database and never migrates, truncates or drops. **Do not recreate/migrate/reset that schema.** If a test fails, inspect the failure before retrying. No real provider traffic is sent during these tests.

**NOT RUN / future gates:** real Kavenegar connectivity/charges and delivery receipt, status-query integration, tenant self-service setup, outbound endpoint DNS/IP pinning review, simultaneous send-race stress, operational alerts and cPanel short-lived Cron rehearsal.


## Opt-in tenant-owned settings — source authored, Mac QA pending

In addition to the existing platform-staff account configuration, tenant
owners and client administrators can manage **only their own** Kavenegar
account using:

- `GET /account/clients/{client}/providers/kavenegar`
- `POST /account/clients/{client}/providers/kavenegar`

These session-based pages require a verified login, active client membership
with a `client`-scoped role of `owner` or `client_admin`, active client,
confirmed MFA, and the Super Admin-controlled Pennant feature flag
`client-provider-overrides`. Saving credentials also requires recent password
confirmation and CSRF. The feature is **OFF by default**; the existing
platform-staff configuration pages remain available independently.
No account key or sender value is shown after saving, and security audit
records omit credentials, message contents, OTP and recipient values.

Client portal landing/navigation, invitation/membership onboarding and
independent client role management are **not completed**. A permitted member
can use the direct client-scoped URL after an authorized membership exists;
there is currently no client self-service homepage. Four new negative/positive
feature tests cover feature-off, own-account configuration, wrong tenant/role,
and MFA/password restrictions. **These tests have NOT run on Mac.**

This newly authored self-service source joins the original 24 Kavenegar
tests; the entire new Kavenegar milestone now has **28 additional tests**
relative to the last 116-test verified baseline. The expected complete suite
is **144 tests if all pass**, but no Kavenegar tests have been executed on
the developer Mac. Do NOT enable live sends based on this code alone.


## Manual delivery status inquiry — new source, Mac QA pending (2026-11-18)

The official Kavenegar SDK documents `sms/status` with a provider `messageid`. Arsam Relay now has a separate status-only operation, guarded independently of all send paths:

```ini
ARSAM_KAVENEGAR_STATUS_ENABLED=false
ARSAM_KAVENEGAR_SEND_ENABLED=false
```

The status gate is **OFF by default**. No production deployment or live status call was made. Enabling the read-only status gate permits **network access to Kavenegar** and may reveal the API key in the URL path to host/network diagnostics; review outbound logging and secret-handling controls before enabling even this gate.

A platform administrator with confirmed MFA, recent password confirmation and `clients.manage` may manually query a client-owned `accepted` Kavenegar SMS via Clients → Delivery reviews → **Check provider status**. The POST route is `/clients/{client}/delivery-reviews/{delivery}/check-status` and is capped at six requests/minute by middleware. There is no scheduler or autonomous polling.

The query requires a tenant-bound delivery, an already-accepted durable attempt, matching enabled Kavenegar account and a validated `kv-<numeric id>` provider reference. It sends form-encoded HTTPS POST to the fixed `sms/status` endpoint with **only** `messageid`, validates one matching response and returns a sanitized classification:

- Provider status code `10` → `provider_reports_delivered` (an observation only).
- Other valid codes → `not_confirmed`.
- Invalid, missing, mismatched, failed or timed-out responses → `unknown`.

The signed event `delivery.provider_status_queried` records the opaque delivery ID, one normalized classification and numeric provider status, never raw status text, recipient, body, OTP, credentials or raw response. The result is also briefly shown in a session flash message. **No Delivery state changes, retries, receipts, automatic delivery proof or provider sends occur**, including when code `10` is returned. The operator must independently investigate and later use an approved evidence-based resolution mechanism.

New Unit and Feature tests use `Http::fake`; **NOT YET RUN on the developer Mac**. Check:

```bash
php -l app/Providers/Adapters/KavenegarSmsAdapter.php
php -l app/Services/KavenegarDeliveryStatus.php
php -l app/Http/Controllers/Admin/DeliveryReviewController.php
php -l tests/Unit/KavenegarStatusAdapterTest.php
php -l tests/Feature/DeliveryReviewTest.php
php vendor/bin/pint --test
php artisan test --filter=KavenegarStatusAdapterTest
php artisan test --filter=DeliveryReviewTest
php artisan test
DB_DATABASE=arsamnet_relay_integration_test php scripts/mysql_publish_integration.php --expect=arsamnet_relay_integration_test
```

The isolated MySQL integration runner now includes an **additional** HTTP-faked, tenant-bound `KavenegarDeliveryStatus` lookup against an accepted record, including a no-state-change check. The new assertions are **not yet executed on the developer Mac** and do not establish production status-query readiness. Do not claim status production readiness or make a live provider request based only on these tests.
