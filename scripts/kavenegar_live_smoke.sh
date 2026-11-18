#!/usr/bin/env bash
# Explicitly operator-invoked, at most one live SMS. Never run in CI.
set -euo pipefail
umask 077

if [[ "${1:-}" == "--help" ]]; then
    echo "Usage: bash scripts/kavenegar_live_smoke.sh [--status]"
    echo "Default: choose single, 2-recipient bulk or OTP live test. --status: read-only lookup."
    exit 0
fi
if [[ "${#}" -gt 1 || ( "${#}" -eq 1 && "${1}" != "--status" ) ]]; then
    echo "Unknown option. Try --help." >&2
    exit 2
fi

if [[ ! -t 0 ]]; then
    echo "Interactive terminal required. Refusing unattended SMS or status checks." >&2
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

if [[ "${1:-}" == "--status" ]]; then
    printf 'Message ULID to check (26 uppercase characters): '
    IFS= read -r message_id
    if [[ ! "${message_id}" =~ ^[0-9A-HJKMNP-TV-Z]{26}$ ]]; then
        echo "Invalid message ID." >&2
        exit 2
    fi
    printf 'Relay token with messages.read scope (hidden input): '
    IFS= read -r -s token
    printf '\n'
    if [[ ! "${token}" =~ ^relay_[a-f0-9]{64}$ ]]; then
        echo "Invalid machine token format." >&2
        unset token
        exit 2
    fi
    status_file="$(mktemp "${TMPDIR:-/tmp}/arsam-relay-status.XXXXXXXX")"
    trap 'rm -f -- "${status_file}"' EXIT
    http="$(printf 'header = "Authorization: Bearer %s"\nheader = "Accept: application/json"\n' "${token}" |
        curl --silent --show-error --noproxy '*' --proto '=http' \
            --max-redirs 0 --connect-timeout 2 --max-time 6 --retry 0 \
            --config - --output "${status_file}" --write-out '%{http_code}' \
            "${base_url}/api/v1/sms/messages/${message_id}")" || {
        echo "Local read-only lookup failed." >&2
        unset token
        exit 3
    }
    unset token
    if [[ "${http}" != "200" ]]; then
        echo "Status lookup returned HTTP ${http}; check messages.read scope and tenant ownership." >&2
        exit 3
    fi
    php -r '
        $r=json_decode((string)file_get_contents($argv[1]),true);
        $d=$r["data"]??null;
        if(!is_array($d))exit(1);
        printf("Message: %s\n",$d["message_id"]??"unknown");
        foreach($d["deliveries"]??[] as $v){
            printf("Delivery: %s | state: %s | attempt: %s | outcome: %s | provider ref: %s\n",
                $v["id"]??"unknown",$v["state"]??"unknown",
                $v["attempt_count"]??"unknown",$v["outcome"]??"none",$v["provider_ref"]??"none");
        }
    ' "${status_file}"
    echo "Stored Relay status is not a live Kavenegar delivery receipt."
    exit 0
fi

printf 'Test operation [single/bulk/otp] (default single): '
IFS= read -r mode
mode="${mode:-single}"
case "${mode}" in
    single|bulk|otp) ;;
    *) echo "Invalid operation." >&2; exit 2 ;;
esac

printf 'Owned Iranian mobile test number (09xxxxxxxxx): '
IFS= read -r receptor
if [[ ! "${receptor}" =~ ^09[0-9]{9}$ ]]; then
    echo "Only a normalized Iranian test mobile is allowed." >&2
    exit 2
fi

receptor2=""
template=""
otp=""
if [[ "${mode}" == "bulk" ]]; then
    printf 'Second owned Iranian mobile (different from first): '
    IFS= read -r receptor2
    if [[ ! "${receptor2}" =~ ^09[0-9]{9}$ || "${receptor2}" == "${receptor}" ]]; then
        echo "Bulk test requires two distinct owned Iranian mobile numbers." >&2
        exit 2
    fi
elif [[ "${mode}" == "otp" ]]; then
    printf 'Approved Kavenegar VerifyLookup template name: '
    IFS= read -r template
    if [[ ! "${template}" =~ ^[A-Za-z][A-Za-z0-9_-]{0,63}$ ]]; then
        echo "Invalid VerifyLookup template." >&2
        exit 2
    fi
    printf 'OTP test code (hidden): '
    IFS= read -r -s otp
    printf '\n'
    if [[ ! "${otp}" =~ ^[A-Za-z0-9_-]{4,32}$ ]]; then
        echo "Invalid OTP token." >&2
        unset otp
        exit 2
    fi
fi

printf 'Paste the tenant Relay API token with messages.publish (hidden input): '
IFS= read -r -s token
printf '\n'
if [[ ! "${token}" =~ ^relay_[a-f0-9]{64}$ ]]; then
    echo "The Relay machine token format is invalid." >&2
    unset token
    exit 2
fi

# Read-only machine scope check before allocating a paid trial.
# The token is streamed through stdin, never exposed in process arguments.
auth_file="$(mktemp "${TMPDIR:-/tmp}/arsam-relay-auth.XXXXXXXX")"
body_file=""
response_file=""
readiness_file=""
trap 'rm -f -- "${auth_file}" "${body_file}" "${response_file}" "${readiness_file}"' EXIT
if ! auth_status="$(printf 'header = "Authorization: Bearer %s"\nheader = "Accept: application/json"\n' "${token}" |
    curl --silent --show-error --noproxy '*' --proto '=http' \
      --max-redirs 0 --connect-timeout 2 --max-time 6 --retry 0 \
      --config - --output "${auth_file}" --write-out '%{http_code}' \
      "${base_url}/api/v1/me")"; then
    echo "Local API is unreachable. No SMS requested." >&2
    unset token
    exit 3
fi
if [[ "${auth_status}" != "200" ]] ||
    ! php -r '$r=json_decode((string)file_get_contents($argv[1]),true);exit(is_array($r)&&is_array($r["data"]["scopes"]??null)&&in_array("messages.publish",$r["data"]["scopes"],true)?0:1);' "${auth_file}"; then
    echo "Relay machine token is invalid or lacks messages.publish scope. No SMS requested." >&2
    unset token
    exit 3
fi
echo "Local Relay identity and messages.publish scope verified."

# Check live send gates, tenant route and available quota before creating
# an irreversible local attempt marker. No provider HTTP call occurs here.
required_slots=1
if [[ "${mode}" == "bulk" ]]; then required_slots=2; fi
readiness_file="$(mktemp "${TMPDIR:-/tmp}/arsam-relay-ready.XXXXXXXX")"
if ! ready_http="$(printf 'header = "Authorization: Bearer %s"\nheader = "Accept: application/json"\n' "${token}" |
    curl --silent --show-error --noproxy '*' --proto '=http' \
        --max-redirs 0 --connect-timeout 2 --max-time 6 --retry 0 \
        --config - --output "${readiness_file}" --write-out '%{http_code}' \
        "${base_url}/api/v1/sms/readiness?recipients=${required_slots}")"; then
    echo "Local SMS readiness check failed; no provider request made." >&2
    unset token otp
    exit 3
fi
if [[ "${ready_http}" != "200" ]] ||
    ! php -r '
        $r=json_decode((string)file_get_contents($argv[1]),true);
        $ready=$r["data"]["ready"]??false;
        if($ready!==true){
            echo "Provider route, sending flags or hourly capacity are not ready. No SMS requested.\n";
            exit(1);
        }
    ' "${readiness_file}"; then
    echo "Check System Settings and the test client Kavenegar route; no SMS requested." >&2
    unset token otp
    exit 3
fi
echo "Kavenegar test route, gates and hourly capacity verified."

# A persistent local marker prevents accidental duplicate paid trials
# after a timeout, ambiguous outcome or interrupted terminal session.
state_dir="${HOME}/.arsam-relay"
if [[ -L "${state_dir}" ]]; then
    echo "Refusing a symlinked local trial directory." >&2
    unset token
    exit 2
fi
(umask 077; mkdir -p -- "${state_dir}")
fingerprint="$(printf '%s\n%s\n%s' "${base_url}" "${mode}" "${receptor}" | php -r 'echo hash("sha256", stream_get_contents(STDIN));')"
marker2=""
if [[ "${mode}" == "bulk" ]]; then
    fingerprint2="$(printf '%s\n%s\n%s' "${base_url}" "${mode}" "${receptor2}" | php -r 'echo hash("sha256", stream_get_contents(STDIN));')"
    marker2="${state_dir}/kavenegar-single-${fingerprint2}.attempted"
fi
marker="${state_dir}/kavenegar-single-${fingerprint}.attempted"
if [[ -e "${marker}" || -L "${marker}" || ( -n "${marker2}" && ( -e "${marker2}" || -L "${marker2}" ) ) ]]; then
    echo "An SMS trial already started for this local Relay origin and recipient." >&2
    echo "Review the provider dashboard and delivery attempt before considering any other send." >&2
    unset token
    exit 3
fi

idempotency="live-smoke-$(php -r 'echo bin2hex(random_bytes(16));')"
printf 'Target: %s\n' "${base_url}"
printf 'Recipient: ********%s\n' "${receptor: -3}"
printf 'Operation: %s\n' "${mode}"
if [[ "${mode}" == "bulk" ]]; then
    printf 'Second recipient: ********%s\n' "${receptor2: -3}"
fi
printf 'Payload: fixed harmless text or a hidden OTP token\n'
printf 'Idempotency: %s\n' "${idempotency}"
confirm_text="SEND ONE SMS"
if [[ "${mode}" == "bulk" ]]; then confirm_text="SEND TWO SMS"; fi
if [[ "${mode}" == "otp" ]]; then confirm_text="SEND ONE OTP"; fi
printf '\nREAL, potentially billable provider operation. Type %s to proceed: ' "${confirm_text}"
IFS= read -r confirmation
if [[ "${confirmation}" != "${confirm_text}" ]]; then
    echo "Cancelled. No network call made."
    unset token
    exit 0
fi

# Reserve the trial BEFORE network access. No retry after an ambiguous result.
# noclobber keeps simultaneous terminal runs from both sending.
if ! (set -C; printf 'Started: %s UTC\nIdempotency: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "${idempotency}" > "${marker}") 2>/dev/null; then
    echo "Trial already reserved for this number. No SMS sent by this invocation." >&2
    unset token
    exit 3
fi

# Reserve the second bulk recipient before any network access.
# A failed reservation is safe to leave as a no-send marker, not a retry.
if [[ "${mode}" == "bulk" ]]; then
    if ! (set -C; printf 'Started: %s UTC\nIdempotency: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "${idempotency}" > "${marker2}") 2>/dev/null; then
        echo "Second recipient already reserved. No provider request made." >&2
        unset token otp
        exit 3
    fi
fi

# Curl's secret header is streamed through stdin config, never passed in
# command-line args or printed. No redirects, no retries, bounded timeout.
# The JSON body is made from fixed content + validated digits only.
body_file="$(mktemp "${TMPDIR:-/tmp}/arsam-relay-smoke.XXXXXXXX")"
response_file="$(mktemp "${TMPDIR:-/tmp}/arsam-relay-response.XXXXXXXX")"
# Data (including OTP and recipients) goes over a pipe and into a private
# temporary payload, never a process argument or terminal log.
printf '%s\n' "${mode}" "${receptor}" "${receptor2}" "${template}" "${otp}" |
    php -r '
        $v=file("php://stdin",FILE_IGNORE_NEW_LINES);
        [$mode,$one,$two,$template,$otp]=$v;
        $data=match($mode){
            "single"=>["receptor"=>$one,"message"=>"Arsam Relay live SMS connectivity test"],
            "bulk"=>["items"=>[
                ["receptor"=>$one,"message"=>"Arsam Relay bulk test A"],
                ["receptor"=>$two,"message"=>"Arsam Relay bulk test B"],
            ]],
            "otp"=>["receptor"=>$one,"template"=>$template,"token"=>$otp],
            default=>throw new RuntimeException("Invalid operation"),
        };
        echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    ' > "${body_file}"
unset otp
endpoint="send"
if [[ "${mode}" == "bulk" ]]; then endpoint="bulk"; fi
if [[ "${mode}" == "otp" ]]; then endpoint="otp"; fi
printf 'header = "Authorization: Bearer %s"\nheader = "Idempotency-Key: %s"\nheader = "Content-Type: application/json"\n' "${token}" "${idempotency}" \
    | curl --silent --show-error --noproxy '*' --proto '=http' \
        --max-redirs 0 --connect-timeout 3 --max-time 15 \
        --retry 0 --request POST \
        --config - \
        --data-binary "@${body_file}" \
        --output "${response_file}" --write-out 'HTTP status: %{http_code}\n' \
        "${base_url}/api/v1/sms/${endpoint}"
unset token
php -r '
    $r=json_decode((string)file_get_contents($argv[1]),true);
    $data=$r["data"]??null;
    if(!is_array($data)){echo "No accepted publish response. Check HTTP status; do not retry blindly.\n";exit;}
    printf("Message ID: %s\n",$data["message_id"]??"unknown");
    printf("Overall state: %s\n",$data["state"]??"unknown");
    foreach($data["deliveries"]??[] as $d){
        printf("Delivery: %s | state: %s | provider ref: %s\n",
          $d["id"]??"unknown",$d["state"]??"unknown",$d["provider_ref"]??"none");
    }
' "${response_file}"
echo "Check provider dashboard and the device. Disable live sending flags after the trial."
