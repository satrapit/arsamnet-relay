# Arsam Relay — Current Verification Status

**As of:** 2026-11-17  
**Branch:** \`feat/laravel13-foundation\`  
**PR:** [#1](https://github.com/satrapit/arsamnet-relay/pull/1), **Draft**.  
**Environment:** developer Mac; full Laravel tests use in-memory SQLite. Dedicated real MySQL 8 tests use \`arsamnet_relay_integration_test\`.  
**Safety:** production publish is OFF by default. No live provider adapter or outbound transport is connected.

> This is the authoritative *latest verified result* summary. Older sections in
> individual documentation files are historical progress snapshots; a statement
> saying "not run" may refer to work that was later tested.

## Confirmed PASS (developer-provided Mac output)

| Check | Verified result | Scope |
| --- | --- | --- |
| PHP syntax | PASS | FakeDispatchCoordinator, its tests, and MySQL integration script |
| Focused fake dispatch | **15 tests, 76 assertions PASS** | priority, no fallback, timing, tenant, invalid encrypted data, fake accepted/result lifecycle |
| Full Laravel PHPUnit | **116 tests, 430 assertions PASS** | SQLite in memory, not full-scale concurrency |
| Laravel Pint | **91 PHP files PASS** | no formatting changes |
| Independent real MySQL 8 integration | **PASS** | transaction rollback, idempotency/replay, two-process same-key race, route selection, fenced leases, one attempt, signed fake receipt, scheduled-first-route fake end-to-end |
| Isolated MySQL fixture cleanup | **PASS** | runner returned application test data to empty; migration ledger remains |
| Primary schema verification | **PASS** | \`arsamnet_relay\`: 28 tables including migrations, 192 documented fields, 61 declared indexes, 32 restricted FKs, 4 migration records; read-only |
| Source control | **PASS** | \`git diff --check\` and \`git status --short\` produced no output on Mac after last pull |

**Important limitations:** These outputs demonstrate that the scripted scenarios
passed. They do not establish network delivery, production cPanel compatibility,
complete tenant-penetration testing, high-concurrency load coverage, failure
recovery from a mid-send crash, SMTP/SMS provider contracts or genuine delivery
receipts. The two-process race is one case, not a stress benchmark.

## Implementation boundary

The local/testing-only \`FakeDispatchCoordinator::dispatchFirstEligible\`
uses the read-only \`ProviderRouteSelector\`, then the no-network
\`FakeOutboundProviderAdapter\`; it never falls back after an attempt
or unknown result. A durable \`started\` attempt precedes simulated
provider acceptance. Accepted != delivered; only a separately verified,
fake-only HMAC receipt moves a delivery to delivered.

No public provider callback, real SMS/SMTP/webhook transport, production
send worker, automatic failover, retry or dead-letter consumer is connected.
The fake code is explicitly blocked outside local/testing.

## Safe next steps

1. Review [Provider Go-Live Gates](PROVIDER_GO_LIVE_GATES.md) and choose
   one concrete provider/channel and actual API contract before building
   any network adapter.
2. Implement provider-specific credential validation, transport security,
   error classification and callback/status proof, with test doubles first.
3. Require explicit security review, isolated MySQL tests and an
   operator-approved, strictly scoped sandbox rehearsal before enabling
   real outbound sends.
4. Preserve the **Draft PR**, \`main\`, environment flags, and separate
   development/production data until that review is complete.

### Repeatable regression commands (developer Mac)

\`\`\`bash
cd ~/Sites/arsamnet-relay
php vendor/bin/pint --test
php artisan test

DB_DATABASE=arsamnet_relay_integration_test \
  php scripts/mysql_publish_integration.php \
  --expect=arsamnet_relay_integration_test

php artisan relay:db-verify --expect=arsamnet_relay
git diff --check
git status --short
\`\`\`

**Do not** recreate, drop, wipe or migrate the already existing integration
schema to repeat the script. Never run tests/migrations on \`arsamnet_relay\`.
