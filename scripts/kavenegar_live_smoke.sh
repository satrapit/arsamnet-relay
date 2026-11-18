#!/usr/bin/env bash
# Explicitly operator-invoked, at most one live SMS. Never run in CI.
set -euo pipefail
umask 077

if [[ ! -t 0 ]]; then
    echo "Interactive terminal required. Refusing unattended live SMS." >&2
    exit 2
fi

printf 'Relay HTTPS base URL (example https://relay.example.com): '
IFS= read -r base_url
base_url="${base_url%/}"
if [[ ! "${base_url}" =~ ^https://[A-Za-z0-9.-]+(:443)?$ ]]; then
    echo "A hostname-only HTTPS URL (optional :443) is required." >&2
    exit 2
fi

printf 'Owned Iranian mobile test number (09xxxxxxxxx): '
IFS= read -r receptor
if [[ ! "${receptor}" =~ ^09[0-9]{9}$ ]]; then
    echo "Only a normalized Iranian test mobile is allowed." >&2
    exit 2
fi

printf 'Paste the ONE-TIME ISSUED tenant Relay API token (hidden input): '
IFS= read -r -s token
printf '\n'
if [[ ! "${token}" =~ ^relay_[A-Fa-f0-9]{64}$ ]]; then
    echo "The Relay machine token format is invalid." >&2
    unset token
    exit 2
fi

idempotency="live-smoke-$(php -r 'echo bin2hex(random_bytes(16));')"
printf 'Target: %s\n' "${base_url}"
printf 'Recipient: ********%s\n' "${receptor: -3}"
printf 'Message: Arsam Relay live SMS connectivity test\n'
printf 'Idempotency: %s\n' "${idempotency}"
printf '\nThis is a REAL, possibly billable SMS. Type SEND ONE SMS to proceed: '
IFS= read -r confirmation
if [[ "${confirmation}" != 'SEND ONE SMS' ]]; then
    echo "Cancelled. No network call made."
    unset token
    exit 0
fi

# Curl's secret header is streamed through stdin config, never passed in
# command-line args or printed. No redirects, no retries, bounded timeout.
# The JSON body is made from fixed content + validated digits only.
body="{\"receptor\":\"${receptor}\",\"message\":\"Arsam Relay live SMS connectivity test\"}"
body_file="$(mktemp "${TMPDIR:-/tmp}/arsam-relay-smoke.XXXXXXXX")"
trap 'rm -f -- "${body_file}"' EXIT
printf '%s' "${body}" > "${body_file}"
unset body
printf 'header = "Authorization: Bearer %s"\nheader = "Idempotency-Key: %s"\nheader = "Content-Type: application/json"\n' "${token}" "${idempotency}" \
    | curl --silent --show-error --proto '=https' --tlsv1.2 \
        --max-redirs 0 --connect-timeout 3 --max-time 15 \
        --retry 0 --request POST \
        --config - \
        --data-binary "@${body_file}" \
        --write-out '\nHTTP status: %{http_code}\n' \
        "${base_url}/api/v1/sms/send"
unset token
echo "Check the matching delivery and attempt in the admin panel. Disable live sending after the trial."
