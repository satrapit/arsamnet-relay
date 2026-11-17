# ERD — review required

The canonical proposed diagram is docs/ERD.dbml (27 tables, 192 columns, 32 relationships). docs/ERD.csv is a column dictionary. docs/dbdiagram-csv contains one synthetic import CSV per table. Use DBML in dbdiagram.io for foreign keys and indexes; a single CSV is NOT a complete ERD interchange format.

All four initial migration files have now been executed on dedicated local MySQL 8.0.33, and a read-only metadata inspection found two missing diagram references that have since been documented. Official DBML import, formal owner schema signoff and MySQL concurrency tests remain pending.

## Database vs application invariants

Composite tenant references protect subscriptions and deliveries. Application logic must also ensure each platform_role_assignments.role_id has platform scope, each client_memberships.role_id has client scope, provider routes reference shared or same-client accounts, route and provider channels agree, templates/API keys are owned or shared appropriately, and callback data cannot cross clients.

MySQL nullable unique keys do not guarantee uniqueness for shared templates with NULL client_id. Retention, encryption key rotation, payload hashes, provider result ambiguity, lease fencing, idempotency and quotas need application decisions/tests.

Next gate: rerun read-only `relay:db-verify` after correcting the ERD, verify MySQL tenant isolation on a **separate disposable test database**, complete official DBML import and written owner signoff before non-disposable schema changes.

## Repeatable field-name drift gate

The new `python3 scripts/check_migration_parity.py` checks table and column **names** between this DBML and PHP Schema::create declarations. An independent read-only comparison of actual GitHub sources found 27/27 matching table names and 192/192 field names. This provides no assurance about types, nullability, defaults, composite FKs, indexes, MySQL foreign-key compatibility, transaction behavior, or rollback. Signoff and real MySQL execution remain pending.

 
## Metadata verification after first MySQL migration

The developer's Mac has successfully applied all four initial migration files to an empty local MySQL 8 database. This confirms the DDL executes but not that every column, foreign key and index matches the proposed ERD.

A new read-only metadata verifier is available: `php artisan relay:db-verify --expect=arsamnet_relay`. It uses a restricted local parser to extract 27 tables, 192 fields and 32 relationships from this DBML and compares them with `information_schema`; declared indexes are checked too. The verifier remains unexecuted on the real database until the user runs it. No schema drop, rollback or destructive reset is part of this check.

 
## MySQL metadata discrepancy resolved in proposed ERD (2026-11-17)

The actual MySQL verifier reported two **additional valid owner FKs** which already existed in the original migrations but were not represented in the DBML: `subscriptions.client_id -> clients.id` and `deliveries.client_id -> clients.id`. They reinforce tenant ownership. They have now been **added to the DBML only**; no schema mutation is necessary or appropriate. The current ERD therefore defines **27 tables, 192 fields, 32 restrictive foreign keys**.

A new `python3 scripts/check_migration_relationships.py` compares the declared migration FKs against the DBML; a read-only comparison of GitHub sources found 32/32 matching. Run `php artisan relay:db-verify --expect=arsamnet_relay` on the local Mac to confirm the real deployed MySQL metadata is now in sync. Historical counts of 30 earlier in this changelog document describe an earlier DBML revision, not the current structure.
