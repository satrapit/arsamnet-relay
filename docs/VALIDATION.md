# Verification ledger — 2026-11-17

| Check | Result | Notes |
| --- | --- | --- |
| GitHub repository and push permissions | PASS | satrapit/arsamnet-relay is user owned, publicly visible |
| Development branch and commits | PASS | feat/laravel13-foundation created and updated |
| Official Laravel 13 source retrieval | PASS (metadata) | Via GitHub connector |
| ERD + data dictionary in GitHub | PASS | 27-table baseline |
| Per-table CSV generation | PASS (structure only) | 27 files, 192 columns |
| App PHP lint/Composer validation | NOT RUN | Composer missing, required PHP extensions unavailable |
| composer install + lockfile | NOT RUN | No Packagist DNS |
| npm install/build + lockfile | NOT RUN | No package registry DNS |
| Laravel PHPUnit, Pint, DB migrations, rollback | NOT RUN | Dependencies unavailable, schema unapproved |
| Official dbdiagram import | NOT RUN | No account session |
| TailAdmin Free integration | PARTIAL | Custom Tailwind UI; upstream components not imported yet |
| Provider send/queue DLQ and security | NOT IMPLEMENTED | Milestones 3–4 |
| Graphify / cPanel Cron/SSL/DNS | NOT RUN | No connected host |
| Public release/version tag | NOT CREATED | No release gate passed |
| GitHub Actions workflows | NOT CREATED | Explicit requirement |

Committed code is NOT equivalent to tested functionality. No production rollout occurred.

## Follow-up implementation and narrow checks

- PASS: design-pack offline Python validator executed in isolated environment (27 tables, 192 columns, 30 references, 27 CSV examples).
- PASS: PHP `php -l` performed on the exact drafted source for SecurityAudit, SecurityEventRecorder and SecurityMonitorController. No Laravel runtime dependencies were used in those checks.
- PASS: Node `node --check` run on the mobile drawer JavaScript before committing.
- NOT RUN: PHP lint for the entire GitHub branch, PHPUnit, MySQL migrations, Composer and npm installs, Vite build and browser interaction tests.
- Current security monitor is read-only and depends on the configured `ARSAM_AUDIT_HMAC_KEY` and migrated database.

- PASS: `php scripts/offline/AuditIntegritySmoke.php` executed against a temporary local source mirror using minimal stubs: six audit assertions passed. No Composer/Laravel or MySQL code was executed.
- PASS: `php -l` on security audit implementation, event recorder, monitor controller, verification command and offline smoke test.
- NOT RUN: Official DBML parser; MySQL schema/migrations; complete PHP lint and Laravel/PHPUnit suite; Vite production build and cPanel smoke checks.

- PASS: PHP syntax check (php -l) on the newly authored ClientApiKeyController and ApiKeyLifecycleTest, using an exact local source copy before the GitHub write.
- NOT RUN: Laravel feature tests for API key lifecycle or concurrency on MySQL 8; these remain release blockers.

## Continued foundation hardening — 2026-11-17

| Check | Status | Evidence/limits |
| --- | --- | --- |
| IPv4/IPv6 normalization and CIDR matching | PASS | `php scripts/offline/IpCidrSmoke.php` — 23 assertions, no framework/database |
| PHP syntax for newly authored CIDR/controller/tests | PASS | `php -l` on IpCidr, IpCidrSmoke, ClientIpAllowlistController, ClientIpAllowlistTest in local authoring container |
| DBML vs draft migration table and column names | PASS (static comparison) | Live GitHub file content analysis found 27 matching tables, 192 matching columns; no extras |
| Schema-parity CLI good and bad fixtures | PASS (synthetic) | Legacy Python fixture check (now retired); matching fixture passed, deliberate drift failed. Current PHP equivalent uses `php scripts/check_migration_parity.php`. |
| Server Laravel Feature tests / MySQL migrations | NOT RUN | Composer and PHP extensions (mbstring, pdo_mysql, pdo_sqlite) absent; no MySQL test DB |
| Dependency resolution / lockfiles / Vite build | NOT RUN | Registry access unavailable |
| DBML official import, FK/type/default parity | NOT RUN | Only names were compared |
| cPanel/CloudLinux live network/Cron/TLS | NOT RUN | No host access |

No schema signoff, version tag or production readiness claim.

## Real developer Mac dependency/bootstrap results (reported 2026-11-17)

- PASS: PHP 8.3.30, Composer 2.9.5, Node 24.13.0, npm 11.6.2 detected.
- PASS: `composer validate` accepted composer.json with a license warning (addressed in following source revision; not yet rerun).
- PASS: `composer install --prefer-dist` installed 133 dependencies, Laravel 13.42.0, Fortify 1.42.0, Pennant 1.27.0, and wrote a LOCAL composer.lock.
- PASS: `npm install` (102 packages, audit reported no vulnerabilities) wrote a LOCAL package-lock.json; `npm run build` succeeded in Vite 8.3.8. fontaine notice informational only.
- PASS: `php artisan about` bootstrap and `php artisan route:list` (42 routes).
- NOT PASS: initial `php artisan schedule:list` because default database cache tried to access a nonexistent SQLite database file; `CACHE_STORE=array php artisan schedule:list` is a non-DB diagnostic to run next.
- NOT RUN: `artisan test`/PHPUnit, MySQL migrations or rollback, Composer security audit, deployment, final licensing/security signoff.
- FIXED IN SOURCE (needs rerun): standalone Audit/IpCidr smoke scripts moved to scripts/offline/ to avoid Composer PSR-4 scanning; provisional proprietary license added. PHPUnit config now forces ephemeral sqlite :memory: for test safety.
- LOCKFILES: composer.lock and package-lock.json exist only in user's local checkout until explicitly reviewed/committed/pushed.


## Follow-up developer output — unit/feature tests and MySQL connectivity (2026-11-17)

- PASS: `php artisan config:clear`; `composer validate --strict`; optimized Composer autoload generated with no PSR-4 warnings; both offline smoke suites (Audit 6, CIDR 23).
- PASS: `CACHE_STORE=array php artisan schedule:list` lists the minute heartbeat and bounded queue worker; `PDO::getAvailableDrivers()` includes MySQL and SQLite.
- PASS: PHPUnit Unit 2 tests / 8 assertions.
- PARTIAL: PHPUnit Feature 7 passed, 1 failed, 18 skipped by `--stop-on-failure` (35 assertions in output). The stock ExampleTest expected 200 from root; project redirects root to dashboard. Test expectation updated and PHP syntax checked locally, but not yet re-executed on the developer Mac.
- PASS (reported): `composer audit --locked` reported no known security vulnerability advisories.
- BLOCKED: `php artisan db:show` and `migrate:status` returned MySQL error 1049 for `arsamnet_relay` at 127.0.0.1:3306. Developer reports a database exists and is configured; host/port/name must be reconciled read-only before any migrations.
- SOURCE FIX: stop tracking `public/build/.gitignore`, which Vite had removed locally; root .gitignore already excludes generated build assets.
- NOT RUN: Full successful Feature suite, actual MySQL migrations/rollback, package lockfile commits, deploy. Draft PR remains unmerged.


## Full Mac Feature pass and real MySQL connection (2026-11-17)

- **PASS (user log):** PHPUnit Feature **26 tests / 82 assertions** with no failures; Unit **2 tests / 8 assertions**. Together **28 tests / 90 assertions**. These tests use SQLite `:memory:`, NOT MySQL 8.
- **PASS (user log):** MySQL 8.0.33 available at 127.0.0.1:3306 and `DB_DATABASE=arsamnet_relay`. The previous 1049 error was resolved by replacing a hyphen with an underscore in the actual database name. `php artisan db:show` now connects.
- The **393 tables / 510.86 MB** displayed by `db:show` span **other schemas visible to the root user** (e.g. other unrelated projects); they do NOT establish the table count of `arsamnet_relay`.
- `php artisan migrate:status` reports `Migration table not found`, expected before first migration; it does not establish whether the target schema contains unrelated tables.
- **Fix in new branch commit:** Removed Laravel scaffold DatabaseSeeder example user, eliminating a known/default account created by `db:seed`. Added fail-closed, read-only `php artisan relay:db-preflight --expect=arsamnet_relay` command to check local env, no config cache, MySQL 8, exact database and **zero** tables/views.
- **Fix in new branch commit:** `composer setup` no longer overwrites an already set `APP_KEY`; changing it would invalidate encrypted database values.
- **NOT YET RUN:** Three newly written bootstrap-safety Feature tests, actual database preflight, migrations against MySQL 8.0.33, foreign-key constraints on MySQL, rollback, final schema approval.

 
## First real MySQL migration PASS and metadata verification pending — 2026-11-17

- PASS (user Mac log): `php artisan test` completed **31 tests and 96 assertions**, with zero failures. These still use SQLite in memory, not MySQL.
- PASS (user Mac log): `relay:db-preflight --expect=arsamnet_relay` verified a fresh MySQL 8 schema with **0 tables/views** before any migration.
- PASS (user Mac log): `php artisan migrate --database=mysql` created the Laravel `migrations` table and ran **all four project migrations** successfully in batch 1. `php artisan migrate:status` confirms all four are `Ran`.
- The four migrations are designed to create 27 project tables plus 1 Laravel migration ledger; **the exact actual database table/FK/index counts have not yet been independently inspected**.
- SOURCE ADDED (unexecuted on Mac): `SchemaManifest` parser for the supported local DBML subset and read-only `php artisan relay:db-verify --expect=arsamnet_relay` checking exact table names, documented column types/lengths/required fields, declared unique/primary and ordinary indexes, 30 FK definitions and restrict-on-delete behavior, and four migration entries.
- SOURCE ADDED (unexecuted on Mac): `SchemaManifestTest` (2 Unit cases) and `DatabaseSchemaVerificationSafetyTest` (2 Feature cases) protect manifest parsing and verifier failure in non-local/test environments.
- NOT RUN: the newly authored four tests, read-only MySQL schema verifier, MySQL rollback, concurrent inserts/rotations against MySQL, production/cPanel rehearsal. **Do not perform destructive reset on the now-populated schema.**

 
## 35 green tests, schema verifier drift and Laravel Pint (2026-11-17)

- PASS (developer Mac): `php artisan test` completed **35 tests, 108 assertions**. Four newly added schema-parser/verification-safety tests passed.
- PASS: the four MySQL migration records remain in batch 1; no schema changes or reset were run.
- `php artisan relay:db-verify --expect=arsamnet_relay` reported exactly two extra FKs: `subscriptions(client_id)->clients(id)` and `deliveries(client_id)->clients(id)`. These **already exist** in the deployed MySQL schema and in the initial migration code; the proposed DBML documentation omitted them.
- FIXED IN SOURCE (not yet rerun on the developer Mac): Added both relationships to `docs/ERD.dbml`. The DBML now has **27 tables, 192 fields, 32 references**. Updated `SchemaManifest`, the unit test, and the offline design validator. Added `scripts/check_migration_relationships.php`, a source-level guard against ERD/Migration FK drift.
- PASS (read-only source inspection): all **32 DBML foreign keys correspond exactly to 32 FK declarations in the four migration files**. This is not the same as a passing MySQL verifier run after the fix.
- `./vendor/bin/pint --test` found **20 style issues in 61 files**. These are code-formatting issues, not evidence of failing feature tests or database integrity. Run `./vendor/bin/pint` on the Mac, then rerun `./vendor/bin/pint --test` and `php artisan test` and commit the autoformatted changes.
- UNTRACKED on Mac: real `composer.lock` and `package-lock.json`. Review and commit those, never invent them from logs.
- NOT RUN: post-fix MySQL verifier, post-format Pint check, MySQL runtime concurrency tests, deployment. PR remains draft.

## Final schema verification and CSV dictionary issue (2026-11-17)

- PASS (developer Mac): full `php artisan test` after code formatting — **35 tests, 110 assertions**.
- PASS (developer Mac): `relay:db-verify --expect=arsamnet_relay` — **28 tables including migrations**, **192 documented fields**, **61 indexes**, **32 restricted foreign keys**, and **four migration records**. No schema discrepancy remains.
- PASS (developer Mac): `./vendor/bin/pint` corrected 20 formatting issues; subsequent `./vendor/bin/pint --test` scanned 61 PHP files with no violations; Composer strict validation also passed.
- PASS: offline `check_migration_parity.py` (27/192) and `check_migration_relationships.py` (32 FKs).
- FAIL at time of user run: `scripts/validate_design.php` because `docs/ERD.csv` contains two non-CSV sheet-description lines before its header. The content after those lines has **exactly 192 data records** and all table/column/type/index values match DBML; the source CSV preamble was removed in this commit, and validator header/identity/type checks improved. Run the script again on Mac; **not yet confirmed after source fix**.
- PENDING: developer's local Pint changes (~20 tracked PHP files), composer.lock and package-lock.json remain uncommitted on Mac. Commit after review and passing validators; do not add .env.

 
## QA tooling migrated to PHP (2026-11-17)

- Developer's last reported pre-change results: **35 Laravel tests/110 assertions PASS**, real MySQL schema verifier **28 tables, 192 fields, 61 indexes, 32 restricted FKs, 4 migration records PASS**, Pint **61 files PASS**, strict Composer validation PASS, and all three legacy Python offline checks PASS.
- Per user instruction, the **three Python scripts are now replaced** with dependency-free PHP scripts and one shared PHP validator. Tests are implemented in PHP by default. No dependency or MySQL table changes.
- PASS (authoring environment): `php -l` on authored PHP validator and wrappers plus new PHPUnit test source. The PHP offline validation logic passed against a representative temporary 27-table / 192-field / 32-FK fixture with 27 CSV samples. Four intentionally invalid cases (unsupported schema alteration, unsupported FK deletion, duplicate migration columns and CSV type drift) were rejected.
- NOT RUN YET (developer Mac): `php scripts/validate_design.php`, `php scripts/check_migration_parity.php`, `php scripts/check_migration_relationships.php`, new PHPUnit cases or Pint against these new files. The earlier Python output is historical only.
- Local development checkout still has user-generated Pint changes and `composer.lock` / `package-lock.json` uncommitted. Review and push those in separate commits when current PHP checks pass.

 
## PHP-only QA first real run and scalar FK parser fix (2026-11-17)

- Mac full Laravel test run: **39 passed / 1 failed, 114 assertions**, with only `OfflineDesignChecksTest::test_all_migration_foreign_keys_match_the_design` failing.
- Mac `php scripts/validate_design.php`: PASS 27 tables, 192 fields, 32 relationships, 27 sample CSVs. `php scripts/check_migration_parity.php`: PASS 27/192.
- Mac `php scripts/check_migration_relationships.php`: FAIL `Unsupported FK columns: 'delivery_id'`. Root cause: new PHP validator's `phpColumns()` recognized only composite PHP array syntax, not the valid single-column `foreign('delivery_id')->references('id')` form already used in migration files.
- Source FIX: recognize both strictly quoted single-column names and bracketed arrays. Added regression tests for scalar/composite FKs and rejecting dynamically constructed column expressions. The underlying migrations, DBML and MySQL schema are **unchanged**.
- PASS (authoring environment): standalone PHP 8.4 syntax check and offline 27-table/192-field/32-FK fixture checks, including scalar/composite FK positive checks and a negative dynamic-expression check. Mac rerun of new commit is **pending**; do not mark the entire suite PASS yet.
- PASS (developer Mac): `relay:db-verify` independently confirmed 28 tables including migrations, 192 fields, 61 indexes, 32 restricted FKs and four migration records. `composer validate --strict` PASS.
- Pint on Mac reports **1 formatting issue** in `app/Support/OfflineDesignChecks.php` (66 files scanned); run `php vendor/bin/pint` after retrieving fix, then check `php vendor/bin/pint --test` and rerun full PHPUnit.
- User's local worktree still contains prior Pint changes (including this one PHP class) plus untracked generated `composer.lock` and `package-lock.json`. Preserve that work when pulling a source edit to the overlapping class; stage/review/commit later.


## Draft direct publish / dispatch staging — unverified on developer Mac (2026-11-17)

- Last developer evidence: **43 PHPUnit tests / 118 assertions PASS**, PHP ERD/CSV/migration validation PASS, MySQL schema verification PASS with 28 tables (including migrations), 61 indexes and 32 FKs, Pint PASS across 66 files, strict Composer validation PASS.
- Source ADDED: `app/Services/MessagePublisher.php`, `app/Http/Controllers/Api/PublishMessageController.php`, `app/Jobs/StageDeliveryJob.php`, plus typed exception classes and `tests/Feature/PublishMessagesTest.php` (nine drafted tests).
- Route: `POST /api/v1/messages`; **disabled by default** via `ARSAM_PUBLISH_ENABLED=false`. Existing machine API authentication, CIDR checks and throttle apply. Requires `messages.publish` scope and valid `Idempotency-Key`.
- DB design reused: encrypted messages, tenant-verified delivery targets, same-transaction per-delivery rows and native database queue records; database queue must use same DB connection. Stage worker does not contact any provider. No new migration/DDL.
- **NOT RUN YET**: new Laravel feature tests, Pint, actual database job insert/worker handling after this commit, MySQL transactional concurrency/rollback behavior or production traffic. Do not claim live publish availability or delivery success.
- Local developer still needs to push reviewed Pint formatting and real Composer/npm lockfiles. The new changes touch new PHP paths, routes/api.php, config/relay.php and .env.example, not the old Pint-modified files.


## Publish staging QA — Mac output (2026-11-17)

- PASS (developer Mac): `php artisan test --filter=PublishMessagesTest`: **9/9 tests, 48 assertions**. These cover encrypted persisted payloads, ID-only jobs, idempotency replay and conflicts, tenant ownership, scope gate and inert StageDeliveryJob.
- PASS (developer Mac): complete `php artisan test`: **52/52 tests, 166 assertions**, SQLite in-memory.
- PASS: `php -l` on MessagePublisher, StageDeliveryJob and PublishMessageController.
- PASS (read-only Mac verification): `relay:db-verify --expect=arsamnet_relay` — 28 tables including migrations, 192 fields, 61 indexes, 32 restrict FKs and four migration records.
- PENDING: `php vendor/bin/pint --test` found three style issues across 72 scanned files: `MessagePublisher.php`, `InvalidPublishTargets.php`, and `MessagePublicationConflict.php`. The new code changes align the publisher docblock and compact empty exception bodies; run Pint locally to finish all whitespace/spacing fixes and verify; not yet re-executed against new commit on Mac.
- Git status at time of reported test included ~22 modified PHP source/test files from earlier Pint formatting and untracked local `composer.lock`, `package-lock.json`. Do not lose these changes; review, commit only tracked PHP formatting and the two lockfiles separately, and push before declaring the feature branch reproducible.
- NOT YET RUN: real MySQL concurrency and domain transactional tests on a separate disposable MySQL DB, provider adapters/sends, production deployment and cPanel Cron.

 
## Final Mac regression and Pint pass after initial publish staging — 2026-11-17

- **PASS:** `php vendor/bin/pint` resolved the 3 latest reported formatting issues. A second `php vendor/bin/pint --test` inspected **72 PHP files** with no issues.
- **PASS:** `php artisan test` — **52 tests / 166 assertions**, no failures (configured in-memory SQLite); includes 9 publish tests.
- **PASS:** read-only `php artisan relay:db-verify --expect=arsamnet_relay` — 28 tables (including migrations), 192 documented fields, 61 indexes, 32 restricted FKs, four migration records.
- **PASS:** `composer validate --strict`; `git diff --check` produced no errors.
- Developer Mac `git status --short`: **25 tracked PHP files modified by Pint**, with untracked `composer.lock` and `package-lock.json`. Latest local diff reports 137 insertions and 75 deletions from formatting. These changes exist locally, **have not been pushed** and must be reviewed and committed on the Mac. No secret environment file appeared in reported status.
- **NOT YET RUN:** PHP publish transaction/concurrency integration on separate disposable MySQL; cPanel host verification; provider sending. PR #1 remains Draft, publish default OFF.

 
## Verified lockfile push / prepared MySQL integration (2026-11-17)

- **PASS user Mac**: `bf5e94f` Pint formatting commit (25 files,
  137 additions/75 deletions) and `c007f22` generated
  `composer.lock` (10,148 lines) and `package-lock.json` (2,294 lines)
  were successfully pushed to `origin/feat/laravel13-foundation`.
  `git status --short` showed no output, confirming clean tracked/untracked
  worktree at the time of the user command.
- **PASS user Mac**: `composer validate --strict`, `composer audit --locked`,
  `npm audit --audit-level=high`: no vulnerability advisories reported by
  either audit at that time.
- Prior full PHP regression: **52 passed / 166 assertions**, Pint all 72
  files PASS, MySQL metadata verifier 28 tables, 192 fields, 61 indexes,
  32 foreign keys PASS.
- **SOURCE ADDED**: isolated integration schema creation and real MySQL
  publish verification scripts, with exact environment/schema protection,
  transaction rollback, idempotency replay/conflict, and two PHP processes
  for concurrent requests. PHP CLI syntax checked in the isolated authoring
  environment; **not executed on Mac or against an actual MySQL server**.
- **NOT RUN:** integration database creation and migrations, real MySQL
  concurrency/rollback assertions, provider deliveries, host production QA.
  No database was created/altered from this authoring environment.

 
## Real MySQL 8 transaction/idempotency test PASSED on Mac — 2026-11-17

- **PASS (developer Mac):** read-only `relay:db-preflight --expect=arsamnet_relay_integration_test` confirmed an empty MySQL 8 integration schema (0 tables/views). The isolated schema was created by `scripts/mysql_publish_prepare.php`.
- **PASS:** all four initial migrations ran successfully in the **isolated integration schema**; no migration executed on `arsamnet_relay` during this procedure.
- **PASS:** `scripts/mysql_publish_integration.php --expect=arsamnet_relay_integration_test`, executed with `DB_DATABASE` pointing to the test schema, confirmed actual MySQL rollback of messages/deliveries/native jobs, sequential idempotency, mismatch rejection, inert StageDeliveryJob, **two concurrent PHP processes creating only one message and one delivery**, and fixture cleanup back to empty tables.
- No provider sends, table drops, truncation or migrations performed by the integration runner. This evidence covers one reproducible two-worker race, **not** general load testing or every possible interleaving.
- **SOURCE ADDED AFTER THE PASS:** `DeliveryLeaseManager` internal (no route, no provider adapter) with tenant-scoped claim, 15–120 second leases, token-fenced renew, message-expiry cap, and quarantine of expired uncertain leases to `review_required` rather than automatic retry; ten PHP Feature test cases (8 initial, 2 later) and extension to the isolated MySQL CLI for lease claim/renew/quarantine.
- **NOT RUN:** new lease PHPUnit cases, updated MySQL integration runner and Pint after the lease commits. Older MySQL PASS refers to the previously committed runner, not the expanded lease checks. All code awaits verification on developer Mac. No schema/DDL changed.


## Delivery lease PHP QA result from developer Mac (2026-11-17)

- **PASS:** PHP lint of `app/Services/DeliveryLeaseManager.php`, `tests/Feature/DeliveryLeaseManagerTest.php` and `scripts/mysql_publish_integration.php` (no syntax errors).
- **PASS:** `php artisan test --filter=DeliveryLeaseManagerTest`: **10 passed, 41 assertions**. Checked tenant ownership, duplicate claim rejection, client/target disablement, expiration, fencing token renewal, quarantine, TTL bounds and suspended-client renewal refusal.
- **PASS:** full `php artisan test`: **62 passed, 207 assertions** (SQLite in memory).
- **PASS:** `php vendor/bin/pint --test`: **76 PHP files**. Initial `php vendor/bin/pint` made **one local style-only change** in `tests/Feature/DeliveryLeaseManagerTest.php` (`class_attributes_separation`). This edit is not yet reported committed or pushed.
- **NOT RUN in supplied output:** expanded real-MySQL lease/renew/quarantine checks in `scripts/mysql_publish_integration.php`; no additional MySQL claim/concurrency assertions can be claimed yet.
- Next: run expanded PHP MySQL integration on preexisting, migrated, empty `arsamnet_relay_integration_test`, then inspect and commit only intended local Pint diff and push. Do not recreate or wipe test schema.

 
## Developer Mac confirmed real MySQL lease and clean source control — 2026-11-17

- **PASS:** expanded isolated `scripts/mysql_publish_integration.php` with `DB_DATABASE=arsamnet_relay_integration_test`: actual MySQL transaction rollback, sequential idempotency and mismatch rejection, inert staging, **lease fencing and correct-token renewal, expired-lease quarantine**, simultaneous two-process duplicate suppression, and fixtures cleaned back to empty.
- This ran on the already migrated independent integration schema. No schema reset, migration re-execution, DROP, provider send or changes to `arsamnet_relay` were reported.
- **PASS:** `php vendor/bin/pint --test` across **76 PHP files**.
- Local `tests/Feature/DeliveryLeaseManagerTest.php` Pint-only whitespace change committed as `ea6b919` and pushed to `origin/feat/laravel13-foundation`. `git status --short` returned no output (clean worktree).
- Last reported full PHPUnit run: **62 tests, 207 assertions, all PASS** on SQLite in memory.
- Real live provider response/attempt processing, callback auth, retry conditions, DLQ, load/stress and hosting rehearsal remain **NOT RUN / NOT IMPLEMENTED**.


## Internal provider attempt lifecycle source added (2026-11-17)

- PREVIOUSLY VERIFIED on developer Mac: isolated MySQL 8 transaction rollback,
  idempotency, two-process race, fenced leasing and expiry quarantine PASS;
  62 Laravel PHPUnit tests/207 assertions PASS; Pint 76 files PASS; local
  one-file style commit `ea6b919` pushed with clean worktree.
- NEW SOURCE (not yet executed on Mac): `App\Enums\ProviderAttemptOutcome`
  and `App\Services\DeliveryAttemptManager`. Internal provider-free
  `begin` records the first and only `started` attempt under a current
  lease, validates tenant/route/channel and uses a single DB transaction.
  Internal `finish` atomically stores sanitized accepted, rejected,
  retryable or ambiguous outcomes and clears the lease. No delivery receipt
  is ever marked `delivered` here; uncertainty requires manual review.
- NEW PHP tests: ten `tests/Feature/DeliveryAttemptManagerTest.php` cases.
  Expanded separate MySQL integration runner to persist fake provider
  account/route and two outcome scenarios and clean dependent FK rows.
- **NOT RUN**: the newly added PHP Feature cases, Pint on new files,
  expanded MySQL attempt runner, real provider calls, callback/ACK,
  status queries, safe auto-retry, production hosting tests.
- No migration or database schema changes, no new route or sender job.
  Publish remains disabled by default. PR #1 remains Draft.


## Provider attempt Mac verification — 2026-11-17 (confirmed)

- **PASS**: all four new PHP files syntax checked: ProviderAttemptOutcome, DeliveryAttemptManager, DeliveryAttemptManagerTest, and mysql_publish_integration.
- **PASS**: `php artisan test --filter=DeliveryAttemptManagerTest`: **10 tests, 50 assertions**.
- **PASS**: full `php artisan test`: **72 tests, 257 assertions**; regular PHPUnit uses SQLite in memory.
- **PASS**: `php vendor/bin/pint --test`: **79 PHP files**. The immediately preceding Pint fix applied one local style-only change in `scripts/mysql_publish_integration.php`; this local difference was **not yet committed or pushed** in the reported terminal output.
- **PASS**: independent real MySQL 8 CLI integration on `arsamnet_relay_integration_test`: rollback of message/delivery/native queue job, sequential idempotency, request conflict rejection, inert StageDeliveryJob, lease claim/renew/quarantine, two-process same-key race, **durable attempt insertion**, accepted-not-delivered status and duplicate finish rejection, ambiguous-result quarantine without automatic retry, and final fixture cleanup to empty.
- **PASS**: read-only `php artisan relay:db-verify --expect=arsamnet_relay`: 28 tables including migrations, 192 documented columns, 61 indexes, 32 restricted foreign keys, four migration records. `git diff --check` found no errors.
- **NOT RUN / NOT IMPLEMENTED**: real outbound provider I/O, signed callback validation, ACK/delivered proof, automated retry/DLQ, concurrency load testing, cPanel production rehearsal. PR remains Draft and Publish defaults OFF.


## Fake-only signed callback receipt groundwork (2026-11-17)

- **LAST CONFIRMED from developer Mac**: isolated MySQL transaction, lease,
  attempt persistence, fake provider accepted/ambiguous outcome and
  two-process idempotency PASS; `72 PHP tests / 257 assertions` PASS;
  Pint `79/79` PASS; local formatting commit `4a6f8fd` pushed with clean
  Git worktree.
- **NEW SOURCE, NOT YET VERIFIED ON MAC**:
  `App\Services\FakeSignedCallbackReceipt` — an internal, no-route,
  no-network fake-provider-only receipt processor restricted to
  `APP_ENV=local/testing`. It uses HMAC-SHA256 over
  `provider_id + timestamp + raw_body` with a >=32-byte fake-only
  callback secret decrypted from that provider's credentials; requires
  a +/-300 second timestamp; strict JSON event ID, provider_ref, status;
  only `status=delivered` after matching exactly one accepted attempt
  and tenant-authorized route. Event ID stored only as HMAC; receipt row
  and `accepted -> delivered` update use a single DB transaction.
  Replayed or tampered events must not modify delivery state.
- **NEW PHP tests:** nine `tests/Feature/FakeSignedCallbackReceiptTest.php`
  cases covering success, duplicate event/second delivery proof, bad
  signature, time skew, unknown provider ref, cross-client provider
  mismatch, disabled route and wrong adapter, nonaccepted attempt and
  production refusal.
- Expanded `scripts/mysql_publish_integration.php` to exercise one
  properly signed fake receipt and replay/tamper rejection, with
  FK-safe cleanup of receipt rows before attempt rows. **Not yet run.**
- **NO new public callback route, real provider adapter, network send,
  schema migration, generic provider verification, or production delivery
  proof.** This fake contract must not be used as a signature algorithm for
  arbitrary SMS/SMTP/third-party providers.


## Fake signed receipt PHP/MySQL QA — developer Mac 2026-11-17

- **PASS:** Full `php artisan test`: **81 tests / 292 assertions**, including nine FakeSignedCallbackReceipt feature cases.
- **PASS:** Isolated real MySQL 8 PHP integration on `arsamnet_relay_integration_test`: rollback, sequential and simultaneous idempotency, staged job, lease fencing and expiry quarantine, durable attempt results, authenticated fake signed delivery receipt applied exactly once, modified/replayed callback rejected, ambiguous result quarantined, and all fixture tables returned empty.
- **PASS:** Read-only `php artisan relay:db-verify --expect=arsamnet_relay`: 28 tables (including migrations), 192 documented columns, 61 indexes, 32 restricted FKs and four migration records.
- **PASS:** `git diff --check` reported no whitespace errors and `git status --short` reported no local changes in the submitted output.
- This evidence **does not** validate a live provider signature, a network callback endpoint, multi-process replay races, production cPanel deployment or guaranteed external delivery. The fake callback service is local/testing-only.
