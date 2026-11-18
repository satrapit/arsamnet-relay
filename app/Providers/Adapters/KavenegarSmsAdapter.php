<?php

namespace App\Providers\Adapters;

use Laravel\Pennant\Feature;
use App\Contracts\Providers\OutboundProviderAdapter;
use App\DTO\OutboundProviderRequest;
use App\DTO\OutboundProviderResult;
use App\Enums\ProviderAttemptOutcome;
use App\Enums\ProviderCapability;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Explicit, fixed-host Kavenegar REST transport. No implicit retries,
 * redirects, dynamic base URLs, response-body logs or secret exceptions.
 *
 * All methods return normalized attempts, never delivery receipts.
 * A real HTTP call additionally requires the global send gate.
 */
final class KavenegarSmsAdapter implements OutboundProviderAdapter
{
    private const HOST = 'https://api.kavenegar.com/v1/';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $sender,
    ) {
        if (! preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D', $apiKey)
            || ! preg_match('/\A[A-Za-z0-9]{2,32}\z/D', $sender)) {
            throw new InvalidArgumentException('Invalid Kavenegar account settings.');
        }
    }

    public function code(): string
    {
        return 'kavenegar';
    }

    public function channel(): string
    {
        return 'sms';
    }

    /** @return list<ProviderCapability> */
    public function capabilities(): array
    {
        return [ProviderCapability::Send];
    }

    public function send(OutboundProviderRequest $request): OutboundProviderResult
    {
        if ($request->channel() !== 'sms') {
            throw new InvalidArgumentException('Kavenegar only supports SMS.');
        }

        $this->assertReceptor($request->destination());

        return $this->single('sms/send', [
            'receptor' => $request->destination(),
            'sender' => $this->sender,
            'message' => $request->content(),
        ]);
    }

    /**
     * One physical Kavenegar sendarray call for several different messages.
     * The returned array always preserves each original input index.
     *
     * @param  list<array{receptor:string,message:string}>  $recipients
     * @return list<OutboundProviderResult>
     */
    public function sendBulk(array $recipients): array
    {
        if (count($recipients) < 1 || count($recipients) > 25 || ! array_is_list($recipients)) {
            throw new InvalidArgumentException('Bulk SMS requires between 1 and 25 recipients.');
        }

        $phones = [];
        $messages = [];
        foreach ($recipients as $item) {
            $receptor = $item['receptor'] ?? null;
            $message = $item['message'] ?? null;
            if (! is_string($receptor) || ! is_string($message)
                || $message === '' || strlen($message) > 4096) {
                throw new InvalidArgumentException('Invalid bulk SMS item.');
            }
            $this->assertReceptor($receptor);
            $phones[] = $receptor;
            $messages[] = $message;
        }

        $response = $this->post('sms/sendarray', [
            'receptor' => json_encode($phones, JSON_THROW_ON_ERROR),
            'sender' => json_encode(array_fill(0, count($phones), $this->sender), JSON_THROW_ON_ERROR),
            'message' => json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);

        return $this->normalize($response, count($recipients));
    }

    /**
     * Send an OTP token through an approved Kavenegar VerifyLookup template.
     * The caller owns token creation, verification, expiry and resend policy.
     */
    public function sendOtp(string $receptor, string $template, string $token, ?string $token2 = null, ?string $token3 = null): OutboundProviderResult
    {
        $this->assertReceptor($receptor);
        if (! preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/D', $template)
            || ! $this->validToken($token)
            || ($token2 !== null && ! $this->validToken($token2))
            || ($token3 !== null && ! $this->validToken($token3))) {
            throw new InvalidArgumentException('Invalid Kavenegar VerifyLookup parameters.');
        }

        $parameters = ['receptor' => $receptor, 'template' => $template, 'token' => $token];
        if ($token2 !== null) {
            $parameters['token2'] = $token2;
        }
        if ($token3 !== null) {
            $parameters['token3'] = $token3;
        }

        return $this->single('verify/lookup', $parameters);
    }

    /**
     * Provider status is a read-only network operation, never a delivery proof
     * or a resend. Only the numeric ID from our verified accepted attempt can
     * be supplied by the caller. Never expose raw provider response fields.
     *
     * @return array{state:string,provider_code:?int}
     */
    public function queryStatus(string $messageId): array
    {
        if (! preg_match('/\A[0-9]{1,18}\z/D', $messageId)) {
            throw new InvalidArgumentException('Invalid provider message ID.');
        }

        $response = $this->post('sms/status', ['messageid' => $messageId], true);
        $body = $response['body'];
        if ($response['http_status'] !== 200 || ! is_array($body)
            || ($body['return']['status'] ?? null) !== 200) {
            return ['state' => 'unknown', 'provider_code' => null];
        }

        $entries = $body['entries'] ?? null;
        if (! is_array($entries) || ! array_is_list($entries) || count($entries) !== 1) {
            return ['state' => 'unknown', 'provider_code' => null];
        }

        $entry = $entries[0];
        $id = is_array($entry) ? ($entry['messageid'] ?? null) : null;
        $code = is_array($entry) ? ($entry['status'] ?? null) : null;
        if ((! is_int($id) && ! is_string($id))
            || (string) $id !== $messageId
            || (! is_int($code) && ! (is_string($code) && preg_match('/\A[0-9]{1,3}\z/D', $code)))
            || (int) $code < 0 || (int) $code > 999) {
            return ['state' => 'unknown', 'provider_code' => null];
        }

        $code = (int) $code;

        return [
            'state' => $code === 10 ? 'provider_reports_delivered' : 'not_confirmed',
            'provider_code' => $code,
        ];
    }

    /** @param array<string,string> $params */
    private function single(string $path, array $params): OutboundProviderResult
    {
        return $this->normalize($this->post($path, $params), 1)[0];
    }

    /** @param array<string,string> $params
     * @return array{http_status:int,body:?array}
     */
    private function post(string $path, array $params, bool $statusOnly = false): array
    {
        if ($statusOnly ? ! Feature::globally()->active('provider-status-queries')
            : ! Feature::globally()->active('kavenegar-sending')) {
            throw new LogicException('Kavenegar network operation is disabled.');
        }

        // Credentials appear in Kavenegar's official URL path. Do not log
        // this URI, its RequestException, outbound body, or HTTP trace.
        $url = self::HOST.$this->apiKey.'/'.$path.'.json';
        try {
            $response = Http::acceptJson()->asForm()
                ->timeout(8)
                ->withOptions([
                    'connect_timeout' => 3,
                    'allow_redirects' => false,
                    'verify' => true,
                ])
                ->post($url, $params);

            $data = $response->json();

            return [
                'http_status' => $response->status(),
                'body' => is_array($data) ? $data : null,
            ];
        } catch (Throwable) {
            // Network failure may mean provider accepted the send already.
            // Do not retry automatically, and never expose HTTP exception.
            return ['http_status' => 0, 'body' => null];
        }
    }

    /**
     * @param  array{http_status:int,body:?array}  $response
     * @return list<OutboundProviderResult>
     */
    private function normalize(array $response, int $count): array
    {
        $http = $response['http_status'];
        $body = $response['body'];
        $status = is_array($body) ? ($body['return']['status'] ?? null) : null;
        $status = is_numeric($status) ? (int) $status : null;
        $safeHttp = $http >= 100 && $http <= 599 ? $http : null;

        if ($http === 200 && $status === 200) {
            $entries = $body['entries'] ?? null;
            if (is_array($entries) && array_is_list($entries) && count($entries) === $count) {
                $results = [];
                $seen = [];
                foreach ($entries as $entry) {
                    $id = is_array($entry) ? ($entry['messageid'] ?? null) : null;
                    if ((! is_string($id) && ! is_int($id))
                        || ! preg_match('/\A[0-9]{1,18}\z/D', (string) $id)) {
                        return $this->repeat(ProviderAttemptOutcome::Ambiguous, $count, $safeHttp);
                    }

                    if (isset($seen[(string) $id])) {
                        return $this->repeat(ProviderAttemptOutcome::Ambiguous, $count, $safeHttp);
                    }
                    $seen[(string) $id] = true;

                    $results[] = new OutboundProviderResult(
                        ProviderAttemptOutcome::Accepted, $safeHttp, 'kv-'.(string) $id,
                    );
                }

                return $results;
            }

            return $this->repeat(ProviderAttemptOutcome::Ambiguous, $count, $safeHttp);
        }

        // Only an explicit provider-side validation/authentication error
        // establishes definitive rejection. Missing/malformed HTTP bodies,
        // redirects, server failures and timeouts are always ambiguous.
        if ($status !== null && $status >= 400 && $status <= 499 && $http >= 200 && $http < 500) {
            return $this->repeat(ProviderAttemptOutcome::Rejected, $count, $safeHttp);
        }

        return $this->repeat(ProviderAttemptOutcome::Ambiguous, $count, $safeHttp);
    }

    /** @return list<OutboundProviderResult> */
    private function repeat(ProviderAttemptOutcome $outcome, int $count, ?int $http): array
    {
        return array_fill(0, $count, new OutboundProviderResult($outcome, $http));
    }

    private function assertReceptor(string $receptor): void
    {
        if (! preg_match('/\A09[0-9]{9}\z/D', $receptor)) {
            throw new InvalidArgumentException('Kavenegar receptor must be a normalized Iranian mobile number.');
        }
    }

    private function validToken(string $token): bool
    {
        return strlen($token) >= 1 && strlen($token) <= 64
            && ! preg_match('/[\x00-\x1F\x7F]/', $token);
    }

    /** @return array<string,string> */
    public function __debugInfo(): array
    {
        return ['api_key' => '[REDACTED]', 'sender' => '[REDACTED]'];
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new LogicException('Kavenegar credentials must not be serialized.');
    }
}
