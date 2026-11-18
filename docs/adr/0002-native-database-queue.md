# ADR 0002 — Laravel database queue and domain deliveries

Status: Proposed / review required
Date: 2026-11-17

Decision: Execute jobs with Laravel Database Queue and bounded Cron-launched CLI workers. Keep messages and per-target deliveries/attempts as domain records, never a second queue implementation.

External provider outcomes may be accepted, delivered, safe-retryable, rejected or ambiguous. Ambiguous timeout, especially OTP, is not automatically retried or failed over. Plan fenced worker lease recovery and transactional outbox integration.

Rationale: Avoid double queue engines and unsafe duplication while supporting constrained shared hosting.
