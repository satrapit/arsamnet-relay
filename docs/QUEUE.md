# Native database queue and bounded Cron

Laravel Database Queue is the execution mechanism. Messages, deliveries and delivery attempts track domain facts; they are not a parallel queue engine. Jobs carry only opaque IDs.

Scheduler registers heartbeat and bounded queue:work. Worker parameters: high,default,low queues; stop when empty; at most 25 jobs or 45 seconds; 96 MiB cap; guarded by database overlap lock. cPanel Cron runs php artisan schedule:run once each minute. Validate host PHP CLI, process limits, cache locks and PCNTL behavior.

Future dispatch flow: persist message/deliveries atomically; claim a fenced delivery lease; enforce tenant, expiry, status and provider routing; create attempt before external call; classify accepted, delivered, retryable, rejected, ambiguous. Use exponential backoff with jitter only on safe retries. Never retransmit ambiguous OTP; send to operator review. Implement DLQ, job crash recovery and idempotency tests before live sends.

There is no active delivery job, message publishing route or DLQ processor in the current branch. The Cron schedule itself is not tested on cPanel.
