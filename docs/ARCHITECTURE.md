# Architecture

Arsam Relay is a single Laravel 13 modular monolith with a separate human web session and machine API key boundary. MySQL 8 stores clients, identity, topics/subscriptions, messages, per-recipient deliveries, provider accounts, audit events, Laravel native database jobs and Pennant flags.

## Domains

- Identity/Access: verified human users, scoped platform/client roles, Fortify + mandatory TOTP for platform staff.
- Clients/API: active/suspended client states, hashed high-entropy bearer keys, optional IP allowlist, quotas and scope authorization.
- Messages: topics, subscriptions, encrypted targets, encrypted message payloads, idempotency and per-target delivery states.
- Providers: separate SMS, SMTP and webhook adapters, shared/client-owned encrypted accounts, capability checks and guarded routing.
- Dispatch: native Laravel Database Queue jobs carry IDs only. Domain delivery state is separate; do not duplicate a job queue engine.
- Operations: signed audit events, Cron heartbeat, safe monitoring and Super Admin-only global Pennant flags.
- UI: Blade/Tailwind 4, Vite-bundled Font Awesome; official TailAdmin Free component integration remains pending.

## Reliability design to implement

In one transaction persist message + target deliveries. Queue jobs with IDs only. Worker claims a fenced lease, validates client/provider/expiration, creates attempt then calls adapter using strict timeouts. The provider may accept a message without delivering it. Classify ambiguous outcomes separately; never automatically retry/fail over an uncertain OTP send. Validate signed callbacks before marking delivered; use backoff, jitter and DLQ for retryable outcomes.

## Hosting

Shared cPanel without root, Node runtime, Redis or permanent worker. Scheduler starts bounded short-lived CLI workers with database locks. Build frontend assets locally and deploy together with app. Document and test actual host Cron constraints before launch.

## State

The foundation branch contains identity/client/API scaffolding and proposed schema. No real message publish endpoint or delivery engine exists yet.
