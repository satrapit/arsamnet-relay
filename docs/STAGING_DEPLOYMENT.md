# Arsam Relay — isolated staging deployment and first Kavenegar trial

This is a **manual operator checklist**, not an automatic deployment. The application has no access to your hosting account or SMS credentials through GitHub. The goal is a separate HTTPS staging installation with a separate MySQL 8 database, one isolated test client and one manually initiated SMS.

## 1. Set up isolated hosting

- Create a dedicated staging hostname, e.g. `staging.relay.example.com`, with valid HTTPS/AutoSSL. It must not be the live production hostname.
- Create a **new empty MySQL 8 staging schema and separate database user**. Never reuse the existing production schema, the local `arsamnet_relay` database, or `arsamnet_relay_integration_test`.
- On cPanel, use a checked-out **immutable main commit** in a private directory outside the web root. Point the staging domain's document root only to Laravel's `public/` directory. Do not expose `.env`, `vendor/`, `storage/`, `database/` or Git files through HTTP.
- Use PHP 8.3+ with Laravel-required extensions, locked Composer dependencies and the matching Vite `public/build` output. If Node/Composer are unavailable on shared hosting, build assets on your trusted Mac and transfer only the tested deploy artifacts. Keep writable `storage/` and `bootstrap/cache/` appropriately limited to the application account.

## 2. Private environment configuration

Configure `.env` on the staging host (never commit or paste its secrets):

```dotenv
APP_NAME="Arsam Relay Staging"
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.relay.example.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=YOUR_DEDICATED_STAGING_DB
DB_USERNAME=YOUR_DEDICATED_STAGING_USER
DB_PASSWORD=SET_PRIVATELY
```

Generate distinct strong staging `APP_KEY` and `ARSAM_AUDIT_HMAC_KEY` **once** and retain them safely; rotating these unexpectedly makes stored encrypted SMS/provider credentials unreadable or invalidates audit verification. Keep application secrets in `.env`, but manage runtime sends and quotas in **System → Settings**, not in `.env`.

Do not create a predictable administrator. Use the project's existing explicit first-Super-Admin provisioning process; require verified login, TOTP and secure sessions. Initially disable public access to staging except known tester IPs if host controls permit.

## 3. Database approval is a separate operation

Before any migration, confirm the exact empty staging database and database account in cPanel and document the intended schema. Do **not** run `migrate:fresh`, `migrate:refresh`, or a blind `php artisan migrate` on production or the existing integration test database. Apply the project's approved migration process **only to the confirmed new staging schema**. Take a recoverable snapshot before changes to an already populated staging installation.

After the approved schema exists, run the **read-only** checks in the staging application directory:

```bash
php artisan about
php artisan route:list
php scripts/staging_preflight.php --expect-db=YOUR_DEDICATED_STAGING_DB
```

The preflight checks: `APP_ENV=staging`, debug disabled, HTTPS URL, MySQL 8 identity, migrated tables, encryption/audit keys and that publishing/sending/status query flags are OFF. This command never migrates, sends or activates a feature.

## 4. Prepare exactly one client and one test number

- Create an isolated client, issue a short-lived machine API key scoped only to `messages.publish`, and restrict client API access to the tester's public IP using the client IP allowlist. The Relay token is **not** the Kavenegar secret.
- In **Clients → SMS provider**, set the encrypted Kavenegar key, sender ID and route priority for only that client. Never paste the Kavenegar key into the shell or application source.
- Review upstream outbound HTTP/proxy logging: the official Kavenegar API key appears in a URL path, so full upstream URLs must not be recorded by reverse proxies, cPanel error monitors or debug tools.
- Check the SMS credit, allowed sender and the ownership/consent of the single target phone number.
- After preflight passes, Super Admin can enable `message-publishing` and `kavenegar-sending` in **Settings** and set a conservative hourly limit (1 for an otherwise empty dedicated test client). These switches are potentially billable; do not enable unattended workers or bulk sends during the trial.

## 5. Execute a single real SMS manually

From a trusted Mac terminal on the newest tested `main`, run:

```bash
bash scripts/kavenegar_live_smoke.sh
```

Enter the HTTPS staging origin, your own Iranian test number and the **Relay** scoped API token. The script has a deliberate `SEND ONE SMS` confirmation. It sends one fixed SMS only, with TLS verification, no redirect/retry and a unique idempotency key. The local one-time marker under `~/.arsam-relay/` is written before the request, to block accidental repeated sends after a timeout. The marker is not a provider confirmation.

## 6. Evaluate and switch off

- Compare the Relay response, persisted Delivery ID and provider attempt with the Kavenegar dashboard and the actual device. A provider `accepted` response is **not** an end-user delivery receipt.
- If an operation times out or reports `review_required`, inspect provider evidence using the separate read-only status lookup. **Never blindly rerun** the test or clear its local marker.
- Disable both `kavenegar-sending` and `message-publishing` immediately after the trial. Revoke the temporary Relay machine key and disable the test route.
- Record only sanitized results: UTC timestamp, request/message/Delivery ID, HTTP code, normalized outcome, provider reference, device receipt and any uncertainty. Never collect or post provider API keys, Relay tokens, OTP codes, message bodies or raw mobile numbers.

**Release boundary:** a passing GitHub CI verifies source-level checks, not cPanel configuration or the actual provider network. Do not treat a merged PR or a successful preflight as proof that a real SMS was delivered.
