# Authorization policy

Deny by default; machine API keys never inherit human web roles.

Platform roles: super_admin, platform_admin, support. Client roles: client_owner, client_manager, client_developer, client_viewer. The DB foreign key to roles cannot enforce scope; role assignment actions must validate scope explicitly.

Current gates: dashboard viewing requires active platform staff, verified email and confirmed TOTP. Feature flags view requires Super Admin/Platform Admin, write requires only Super Admin and recent password confirmation. Client creation and token lifecycle require Super Admin/Platform Admin and recent confirmation.

Machine keys identify one client only, have allowlisted scopes and optional source-IP CIDR restrictions. No unauthenticated publishing routes. Future client message queries must derive tenant from key or active membership, not a submitted client ID. Support roles must not read encrypted messages or OTP data.

Remaining: memberships UI, granular client role Policies, platform override audit and cross-tenant negative tests.

## IP allowlist administration

Platform Admin and Super Admin may view/add/remove source-IP CIDR rules for any client through `/clients/{client}/ips`; writes require verified session, confirmed TOTP, and recent password confirmation. A rule is always looked up by BOTH its own ID and client ID to prevent crossing tenant boundaries. CIDRs are canonicalized, /0 disallowed, 64 rules/client maximum, and changes are fail-closed if a security audit cannot be recorded. The last enabled rule cannot be deleted without explicit `UNRESTRICT` confirmation. **Empty enabled allowlist grants unrestricted source-IP access to holders of valid machine keys**; it does not disable token validation, rate limits or tenant checks.
