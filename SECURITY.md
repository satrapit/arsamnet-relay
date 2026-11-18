# Security policy

This project is pre-release and the repository is currently public. Do not deploy it to production as a messaging broker until the review gates pass. Report discovered vulnerabilities privately to the repository owner through an appropriate private channel rather than publishing exploit details. No supported production versions exist yet.

Never put credentials, OTP, machine keys, real recipient details, production database dumps or service secrets in issues, commits or log excerpts.

Authorization, MFA, CSRF, tenant isolation, provider signature checks and privileged audit must never be made optional feature flags. See docs/SECURITY_BASELINE.md and docs/VALIDATION.md.
