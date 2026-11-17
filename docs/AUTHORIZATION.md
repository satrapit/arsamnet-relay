# Authorization policy

Deny by default; machine API keys never inherit human web roles.

Platform roles: super_admin, platform_admin, support. Client roles: client_owner, client_manager, client_developer, client_viewer. The DB foreign key to roles cannot enforce scope; role assignment actions must validate scope explicitly.

Current gates: dashboard viewing requires active platform staff, verified email and confirmed TOTP. Feature flags view requires Super Admin/Platform Admin, write requires only Super Admin and recent password confirmation. Client creation and token lifecycle require Super Admin/Platform Admin and recent confirmation.

Machine keys identify one client only, have allowlisted scopes and optional source-IP CIDR restrictions. No unauthenticated publishing routes. Future client message queries must derive tenant from key or active membership, not a submitted client ID. Support roles must not read encrypted messages or OTP data.

Remaining: memberships UI, granular client role Policies, platform override audit and cross-tenant negative tests.
