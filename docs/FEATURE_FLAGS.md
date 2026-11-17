# Feature flags — Pennant database driver

The four Boolean flags are webhook-delivery, scheduled-messages, client-provider-overrides and provider-status-queries. Missing flag resolves false. Only Super Admin may update global state after password confirmation; Platform Admin may read. Audit logs flag name and Boolean transition without secrets.

Backend checks are mandatory on each feature path, not merely visibility. Since provider/messaging features are not yet implemented, flags alone do not enable working dispatch.

Current UI supports global scope only. Client-scoped overrides, change actor/timestamps list, consistency checks across workers and safe rollback UI are planned.

Never flag off authentication, admin MFA, CSRF, authorization, cryptography, tenant isolation or webhook signature checks.
