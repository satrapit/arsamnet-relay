# First real Kavenegar SMS smoke — strictly operator initiated

**Status:** Preparation only. No real SMS has been sent by development or CI. This runbook authorizes no unattended or bulk traffic.

## Safe preflight

1. Deploy the *tested* development build to an **isolated staging hostname** behind HTTPS; keep production data, real customer tenants and the original MySQL database out of this trial. Do not merge or deploy on the strength of mocked tests alone. Verify the exact branch/commit and run `php artisan about`, `php artisan route:list`, and the non-destructive DB verifier.
2. Configure one Kavenegar account and sender in **Clients → SMS provider** using the encrypted admin form. Use the real API key there, never in Git, email, .env, terminal output or the test script. Enable its route only for the dedicated test client.
3. Create a short-lived **Relay API key** for that same test client with only `messages.publish`. It is distinct from the Kavenegar API key and appears just once in the UI; keep it private. Use a client IP allowlist that includes the testing machine. A 403/401/422 is an authorization/configuration issue — do not bypass controls.
4. In **System → Feature Flags**, only Super Admin can enable `message-publishing` and `kavenegar-sending`. Choose a conservative `kavenegar-hourly-limit` (for example **1** for a dedicated empty test client). Do **not** enable any other clients or any scheduler/automated live dispatch. Use recent password confirmation and TOTP.
5. Check server logs/redaction: Kavenegar official API includes its API key in the URL path. PHP exception/HTTP debugging, cPanel/proxy request tracing and monitoring must not capture the full upstream URL. Do not use `curl -v` or print credentials.

## Execute exactly one test

On a trusted interactive Mac terminal with the application checked out, run:

```bash
bash scripts/kavenegar_live_smoke.sh
```

The script prompts for the **HTTPS Relay staging URL**, **your own Iranian test mobile number** and the **scoped Relay token** (hidden input). It sends one fixed, harmless SMS only after typing `SEND ONE SMS`. It uses a fresh idempotency key, a single POST to `/api/v1/sms/send`, no redirects, no curl retries, TLS validation, and bounded connection/total timeouts.

**Never rerun after timeout/unknown outcome** without first inspecting the persisted message ID, Delivery, attempt log and Kavenegar panel. An unknown provider outcome may already have incurred a send. A fresh script invocation has a different idempotency key, so repeated invocation is a **new** SMS request.

## Review and rollback

- A successful API response should be correlated to one stored Delivery and one durable Attempt. A Kavenegar `accepted` result only means accepted by provider, **not** delivered to recipient. The provider-status query is a separate gated operation.
- Review the actual test device and Kavenegar account dashboard; confirm billing/identity, sender, latency, UTF-8 and expected text. Reconcile ambiguous attempts manually without retry.
- **Immediately turn OFF** `kavenegar-sending` and `message-publishing` in the Super Admin UI after the trial. Disable/revoke the temporary Relay API key and test provider route. Do not expose tokens or OTPs in screenshots/support logs.
- Keep PR #1 Draft until the responsive UI browser check, secret-safe transport review, environment-specific smoke and merge-conflict checks have passed.

The interactive script is **not a PHPUnit/CI test**. The offline PHPUnit suite and isolated MySQL runner must never enable live SMS or use a real Kavenegar API key.
