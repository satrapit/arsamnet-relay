<?php

namespace Tests\Unit;

use App\DTO\OutboundProviderRequest;
use App\Enums\ProviderAttemptOutcome;
use App\Providers\Adapters\KavenegarSmsAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class KavenegarSmsAdapterTest extends TestCase
{
    private const KEY = 'TEST_KAVENEGAR_KEY_12345678901234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('relay.kavenegar_send_enabled', true);
        Http::preventStrayRequests();
    }

    private function adapter(): KavenegarSmsAdapter
    {
        return new KavenegarSmsAdapter(self::KEY, '10001234');
    }

    /** @return array<string,mixed> */
    private function success(array $ids): array
    {
        return [
            'return' => ['status' => 200, 'message' => 'OK'],
            'entries' => array_map(fn ($id): array => ['messageid' => $id], $ids),
        ];
    }

    public function test_single_uses_fixed_https_host_and_form_request(): void
    {
        Http::fake(['*' => Http::response($this->success([12345]), 200)]);

        $result = $this->adapter()->send(new OutboundProviderRequest(
            (string) Str::ulid(), 'sms', '09123456789', 'سلام کاوه نگار'
        ));

        $this->assertSame(ProviderAttemptOutcome::Accepted, $result->outcome);
        $this->assertSame('accepted', $result->deliveryState());
        $this->assertSame('kv-12345', $result->providerReference);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.kavenegar.com/v1/'.self::KEY.'/sms/send.json'
                && $request['receptor'] === '09123456789'
                && $request['sender'] === '10001234'
                && $request['message'] === 'سلام کاوه نگار';
        });
        Http::assertSentCount(1);
    }

    public function test_sendarray_uses_one_request_with_json_encoded_arrays_and_index_mapping(): void
    {
        Http::fake(['*' => Http::response($this->success([111, 222]), 200)]);
        $results = $this->adapter()->sendBulk([
            ['receptor' => '09123456789', 'message' => 'A'],
            ['receptor' => '09123456780', 'message' => 'B'],
        ]);

        $this->assertCount(2, $results);
        $this->assertSame('kv-111', $results[0]->providerReference);
        $this->assertSame('kv-222', $results[1]->providerReference);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/sms/sendarray.json')
                && json_decode($request['receptor'], true) === ['09123456789', '09123456780']
                && json_decode($request['sender'], true) === ['10001234', '10001234']
                && json_decode($request['message'], true) === ['A', 'B'];
        });
        Http::assertSentCount(1);
    }

    public function test_otp_uses_verify_lookup_and_never_puts_template_or_token_in_url(): void
    {
        Http::fake(['*' => Http::response($this->success([333]), 200)]);
        $result = $this->adapter()->sendOtp(
            '09123456789', 'LoginCode', '123456', 'abc', 'xyz'
        );

        $this->assertSame('kv-333', $result->providerReference);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/verify/lookup.json')
                && ! str_contains($request->url(), '123456')
                && $request['receptor'] === '09123456789'
                && $request['template'] === 'LoginCode'
                && $request['token'] === '123456'
                && $request['token2'] === 'abc'
                && $request['token3'] === 'xyz';
        });
    }

    public function test_provider_business_rejection_is_not_retried(): void
    {
        Http::fake(['*' => Http::response([
            'return' => ['status' => 400, 'message' => 'Secret provider diagnostic'],
            'entries' => [],
        ], 200)]);

        $result = $this->adapter()->sendOtp('09123456789', 'LoginCode', '123456');
        $this->assertSame(ProviderAttemptOutcome::Rejected, $result->outcome);
        $this->assertNull($result->providerReference);
        Http::assertSentCount(1);
        $this->assertStringNotContainsString('Secret provider diagnostic', var_export($result, true));
    }

    public function test_timeout_or_incomplete_response_remains_ambiguous(): void
    {
        Http::fake(['*' => Http::response(['return' => ['status' => 200]], 200)]);

        $result = $this->adapter()->sendOtp('09123456789', 'LoginCode', '123456');
        $this->assertSame(ProviderAttemptOutcome::Ambiguous, $result->outcome);
    }

    public function test_bulk_incomplete_or_duplicate_ids_are_all_ambiguous(): void
    {
        Http::fake(['*' => Http::response($this->success([111]), 200)]);

        $items = [
            ['receptor' => '09123456789', 'message' => 'A'],
            ['receptor' => '09123456780', 'message' => 'B'],
        ];
        $results = $this->adapter()->sendBulk($items);
        $this->assertSame(ProviderAttemptOutcome::Ambiguous, $results[0]->outcome);
        $this->assertSame(ProviderAttemptOutcome::Ambiguous, $results[1]->outcome);

        Http::fake(['*' => Http::response($this->success([222, 222]), 200)]);
        $again = $this->adapter()->sendBulk($items);
        $this->assertSame(ProviderAttemptOutcome::Ambiguous, $again[0]->outcome);
        $this->assertSame(ProviderAttemptOutcome::Ambiguous, $again[1]->outcome);
    }

    public function test_http_error_and_redirect_are_not_retried(): void
    {
        Http::fake(['*' => Http::response('No connection', 503)]);
        $result = $this->adapter()->sendOtp('09123456789', 'LoginCode', '123456');
        $this->assertSame(ProviderAttemptOutcome::Ambiguous, $result->outcome);
        Http::assertSentCount(1);

        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://other.example'])]);
        $result = $this->adapter()->sendOtp('09123456789', 'LoginCode', '123456');
        $this->assertSame(ProviderAttemptOutcome::Ambiguous, $result->outcome);
    }

    public function test_network_gate_prevents_any_request(): void
    {
        config()->set('relay.kavenegar_send_enabled', false);
        Http::fake();

        $this->expectException(LogicException::class);
        try {
            $this->adapter()->sendOtp('09123456789', 'LoginCode', '123456');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_invalid_key_receptor_template_and_channel_fail_closed(): void
    {
        try {
            new KavenegarSmsAdapter('not-safe/key', '10001234');
            $this->fail('Invalid API key allowed.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        try {
            $this->adapter()->sendOtp('00123456789', 'LoginCode', '123456');
            $this->fail('Invalid receptor allowed.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        try {
            $this->adapter()->sendOtp('09123456789', '../bad', '123456');
            $this->fail('Invalid template allowed.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->adapter()->send(new OutboundProviderRequest(
            (string) Str::ulid(), 'email', 'someone@example.test', 'Hello'
        ));
    }

    public function test_credentials_are_redacted_and_not_serializable(): void
    {
        $adapter = $this->adapter();
        $text = var_export($adapter->__debugInfo(), true);
        $this->assertStringNotContainsString(self::KEY, $text);
        $this->assertStringContainsString('[REDACTED]', $text);
        $this->expectException(LogicException::class);
        serialize($adapter);
    }
}
