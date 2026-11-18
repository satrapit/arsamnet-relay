# First real Kavenegar SMS smoke — strictly operator initiated

**Status:** Preparation only. No real SMS has been sent by development or CI. This runbook authorizes no unattended or bulk traffic.

## Safe preflight

1. Deploy the *tested* development build to an **isolated staging hostname** behind HTTPS; keep production data, real customer tenants and the original MySQL database out of this trial. Do not merge or deploy on the strength of mocked tests alone. Verify the exact branch/commit and run `php artisan about`, `php artisan route:list`, and the non-destructive DB verifier.
2. Configure one Kavenegar account and sender in **Clients → SMS provider** using the encrypted admin form. Use the real API key there, never in Git, email, .env, terminal output or the test script. Enable its route only for the dedicated test client.
3. Create a short-lived **Relay API key** for that same test client with only `messages.publish`. It is distinct from the Kavenegar API key and appears just once in the UI; keep it private. Use a client IP allowlist that includes the testing machine. A 403/401/422 is an authorization/configuration issue — do not bypass controls.
4. In **System → Settings**, only Super Admin can enable `message-publishing` and `kavenegar-sending`. Choose a conservative `kavenegar-hourly-limit` (for example **1** for a dedicated empty test client). Do **not** enable any other clients or any scheduler/automated live dispatch. Use an authenticated Super Admin session with TOTP; Settings no longer requires password reconfirmation.
5. Run the **read-only preflight** in the already configured staging application directory:
   \`\`\`bash
   php scripts/staging_preflight.php --expect-db=EXACT_STAGING_DATABASE
   \`\`\`
   It refuses non-staging environments, a mismatched MySQL 8 database, absent migrated tables, missing audit/encryption keys, non-HTTPS origins, debug mode, or operational gates already enabled. It neither migrates nor sends nor changes flags. Set \`APP_ENV=staging\`, \`APP_DEBUG=false\`, and \`APP_URL=https://your-staging-host\` privately on the isolated staging deployment. After preflight PASS, deliberately enable only the needed flags in Super Admin and perform a single manual test. Do **not** run the preflight against real customer databases.
6. Check server logs/redaction: Kavenegar official API includes its API key in the URL path. PHP exception/HTTP debugging, cPanel/proxy request tracing and monitoring must not capture the full upstream URL. Do not use `curl -v` or print credentials.

## Execute exactly one test

On a trusted interactive Mac terminal with the application checked out, run:

```bash
bash scripts/kavenegar_live_smoke.sh
```

The script prompts for the **HTTPS Relay staging URL**, **your own Iranian test mobile number** and the **scoped Relay token** (hidden input). It sends one fixed, harmless SMS only after typing `SEND ONE SMS`. It uses a fresh idempotency key, a single POST to `/api/v1/sms/send`, no redirects, no curl retries, TLS validation, and bounded connection/total timeouts. A local marker under `~/.arsam-relay/` records the first attempted trial for the same origin/number **before** any network call; subsequent runs refuse to send, including after an ambiguous timeout. The marker filename contains only a SHA-256 fingerprint, and the file contains the UTC start and idempotency key (no phone/token). Do not remove markers before investigating the actual provider result.

**Never rerun after timeout/unknown outcome** without first inspecting the persisted message ID, Delivery, attempt log and Kavenegar panel. An unknown provider outcome may already have incurred a send. The script now blocks a second invocation for the same origin and number; do not bypass that protection after an unknown result.

## Review and rollback

- A successful API response should be correlated to one stored Delivery and one durable Attempt. A Kavenegar `accepted` result only means accepted by provider, **not** delivered to recipient. The provider-status query is a separate gated operation.
- Review the actual test device and Kavenegar account dashboard; confirm billing/identity, sender, latency, UTF-8 and expected text. Reconcile ambiguous attempts manually without retry.
- **Immediately turn OFF** `kavenegar-sending` and `message-publishing` in the Super Admin UI after the trial. Disable/revoke the temporary Relay API key and test provider route. Do not expose tokens or OTPs in screenshots/support logs.
- PR #1 and PR #2 were merged into `main` after Mac/CI checks; do not confuse source readiness with staging deployment. The read-only staging preflight is already in `main`. Browser review and a real isolated SMS test remain separate staging gates.

The interactive script is **not a PHPUnit/CI test**. The offline PHPUnit suite and isolated MySQL runner must never enable live SMS or use a real Kavenegar API key.
