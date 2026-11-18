#!/usr/bin/env bash
# Explicitly operator-invoked, at most one live SMS. Never run in CI.
set -euo pipefail
umask 077

if [[ ! -t 0 ]]; then
    echo "Interactive terminal required. Refusing unattended live SMS." >&2
    exit 2
fi

printf 'Local Relay URL (default http://127.0.0.1:8000): '
IFS= read -r base_url
base_url="${base_url:-http://127.0.0.1:8000}"
base_url="${base_url%/}"
if [[ ! "${base_url}" =~ ^http://(127\.0\.0\.1|localhost):[0-9]{2,5}$ ]]; then
    echo "Only a local loopback HTTP origin with explicit port is allowed." >&2
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
if [[ ! "${token}" =~ ^relay_[a-f0-9]{64}$ ]]; then
    echo "The Relay machine token format is invalid." >&2
    unset token
    exit 2
fi

# A persistent local marker prevents accidental duplicate paid trials
# after a timeout, ambiguous outcome or interrupted terminal session.
state_dir="${HOME}/.arsam-relay"
if [[ -L "${state_dir}" ]]; then
    echo "Refusing a symlinked local trial directory." >&2
    unset token
    exit 2
fi
(umask 077; mkdir -p -- "${state_dir}")
fingerprint="$(printf '%s\\n%s' "${base_url}" "${receptor}" | php -r 'echo hash("sha256", stream_get_contents(STDIN));')"
marker="${state_dir}/kavenegar-single-${fingerprint}.attempted"
if [[ -e "${marker}" || -L "${marker}" ]]; then
    echo "An SMS trial already started for this local Relay origin and recipient." >&2
    echo "Review the provider dashboard and delivery attempt before considering any other send." >&2
    unset token
    exit 3
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

# Reserve the trial BEFORE network access. No retry after an ambiguous result.
# noclobber keeps simultaneous terminal runs from both sending.
if ! (set -C; printf 'Started: %s UTC\\nIdempotency: %s\\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "${idempotency}" > "${marker}") 2>/dev/null; then
    echo "Trial already reserved for this number. No SMS sent by this invocation." >&2
    unset token
    exit 3
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
    | curl --silent --show-error --noproxy '*' --proto '=http' \
        --max-redirs 0 --connect-timeout 3 --max-time 15 \
        --retry 0 --request POST \
        --config - \
        --data-binary "@${body_file}" \
        --write-out '\nHTTP status: %{http_code}\n' \
        "${base_url}/api/v1/sms/send"
unset token
echo "Check the matching delivery and attempt in the admin panel. Disable live sending after the trial."
