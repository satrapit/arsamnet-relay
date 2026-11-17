# Provider adapters — future implementation contract

SMS, SMTP email and outbound webhooks will use focused channel/capability interfaces. Provider accounts can be platform-shared or owned by one client; encrypted credentials only. Routes are prioritized and must match tenant/provider channel and ownership. Status queries and delivery callbacks are optional declared capabilities.

An ambiguous network timeout must not trigger automatic retry or failover, especially OTP. A provider response of accepted is not delivered. Create safe sanitized attempt records with opaque provider reference IDs; never persist raw credentials, OTP, message body or recipient address in logs.

For outbound webhook SSRF protection, validate scheme, DNS resolution, both IPv4/IPv6 endpoints, private/loopback/link-local addresses, redirects and DNS rebinding with restricted connect timeouts; validate callback signatures/timestamps before state changes.

No real adapters, callbacks or routing policies have been activated in this foundation branch.
