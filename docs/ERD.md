# ERD — review required

The canonical proposed diagram is docs/ERD.dbml (27 tables, 192 columns, 30 relationships). docs/ERD.csv is a column dictionary. docs/dbdiagram-csv contains one synthetic import CSV per table. Use DBML in dbdiagram.io for foreign keys and indexes; a single CSV is NOT a complete ERD interchange format.

Four migration files currently mirror the proposed table inventory, but they have NOT been parsed against official DBML tooling, executed on MySQL 8 or approved by the owner.

## Database vs application invariants

Composite tenant references protect subscriptions and deliveries. Application logic must also ensure each platform_role_assignments.role_id has platform scope, each client_memberships.role_id has client scope, provider routes reference shared or same-client accounts, route and provider channels agree, templates/API keys are owned or shared appropriately, and callback data cannot cross clients.

MySQL nullable unique keys do not guarantee uniqueness for shared templates with NULL client_id. Retention, encryption key rotation, payload hashes, provider result ambiguity, lease fencing, idempotency and quotas need application decisions/tests.

Next gate: official DBML import, real MySQL migrations/rollback, Laravel Fortify/Pennant table parity and written owner signoff before non-disposable migrations.
