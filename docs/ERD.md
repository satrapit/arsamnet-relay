# ERD — review required

The canonical proposed diagram is docs/ERD.dbml (27 tables, 192 columns, 32 relationships). docs/ERD.csv is a column dictionary. docs/dbdiagram-csv contains one synthetic import CSV per table. Use DBML in dbdiagram.io for foreign keys and indexes; a single CSV is NOT a complete ERD interchange format.

Four migration files currently mirror the proposed table inventory, but they have NOT been parsed against official DBML tooling, executed on MySQL 8 or approved by the owner.

## Database vs application invariants

Composite tenant references protect subscriptions and deliveries. Application logic must also ensure each platform_role_assignments.role_id has platform scope, each client_memberships.role_id has client scope, provider routes reference shared or same-client accounts, route and provider channels agree, templates/API keys are owned or shared appropriately, and callback data cannot cross clients.

MySQL nullable unique keys do not guarantee uniqueness for shared templates with NULL client_id. Retention, encryption key rotation, payload hashes, provider result ambiguity, lease fencing, idempotency and quotas need application decisions/tests.

Next gate: official DBML import, real MySQL migrations/rollback, Laravel Fortify/Pennant table parity and written owner signoff before non-disposable migrations.

## Repeatable field-name drift gate

The new `python3 scripts/check_migration_parity.py` checks table and column **names** between this DBML and PHP Schema::create declarations. An independent read-only comparison of actual GitHub sources found 27/27 matching table names and 192/192 field names. This provides no assurance about types, nullability, defaults, composite FKs, indexes, MySQL foreign-key compatibility, transaction behavior, or rollback. Signoff and real MySQL execution remain pending.

 
## Metadata verification after first MySQL migration

The developer's Mac has successfully applied all four initial migration files to an empty local MySQL 8 database. This confirms the DDL executes but not that every column, foreign key and index matches the proposed ERD.

A new read-only metadata verifier is available: `php artisan relay:db-verify --expect=arsamnet_relay`. It uses a restricted local parser to extract 27 tables, 192 fields and 32 relationships from this DBML and compares them with `information_schema`; declared indexes are checked too. The verifier remains unexecuted on the real database until the user runs it. No schema drop, rollback or destructive reset is part of this check.
