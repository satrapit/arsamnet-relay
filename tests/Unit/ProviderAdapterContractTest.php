<?php

namespace Tests\Unit;

use App\Contracts\Providers\OutboundProviderAdapter;
use App\DTO\OutboundProviderRequest;
use App\DTO\OutboundProviderResult;
use App\Enums\ProviderAttemptOutcome;
use App\Enums\ProviderCapability;
use App\Providers\Adapters\FakeOutboundProviderAdapter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class ProviderAdapterContractTest extends TestCase
{
    private function request(string $channel = 'email'): OutboundProviderRequest
    {
        return new OutboundProviderRequest(
            (string) Str::ulid(),
            $channel,
            'sensitive-recipient@example.test',
            'confidential message body: OTP 123456',
        );
    }

    public function test_fake_adapter_exposes_typed_channel_and_capabilities(): void
    {
        $adapter = new FakeOutboundProviderAdapter('sms');

        $this->assertInstanceOf(OutboundProviderAdapter::class, $adapter);
        $this->assertSame('fake-only', $adapter->code());
        $this->assertSame('sms', $adapter->channel());
        $this->assertSame([
            ProviderCapability::Send, ProviderCapability::SignedDeliveryReceipt,
        ], $adapter->capabilities());
        $this->assertNotContains(ProviderCapability::StatusQuery, $adapter->capabilities());
    }

    public function test_fake_adapter_accepts_only_matching_channel_and_never_claims_delivered(): void
    {
        foreach (['sms', 'email', 'webhook'] as $channel) {
            $adapter = new FakeOutboundProviderAdapter($channel);
            $result = $adapter->send($this->request($channel));

            $this->assertSame(ProviderAttemptOutcome::Accepted, $result->outcome);
            $this->assertSame('accepted', $result->deliveryState());
            $this->assertSame(202, $result->httpStatus);
            $this->assertIsString($result->providerReference);
            $this->assertStringStartsWith('fake-', $result->providerReference);
        }
    }

    public function test_fake_adapter_rejects_mismatched_request_channel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FakeOutboundProviderAdapter('sms'))->send($this->request('email'));
    }

    public function test_fake_adapter_is_unusable_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->expectException(LogicException::class);
            (new FakeOutboundProviderAdapter)->send($this->request());
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }
    }

    public function test_request_debug_output_cannot_leak_content_or_destination(): void
    {
        $request = $this->request();
        $debug = var_export($request->__debugInfo(), true);

        $this->assertStringNotContainsString('sensitive-recipient@example.test', $debug);
        $this->assertStringNotContainsString('OTP 123456', $debug);
        $this->assertStringContainsString('[REDACTED]', $debug);
        $this->assertSame('{}', json_encode($request, JSON_THROW_ON_ERROR));
    }

    public function test_sensitive_request_cannot_be_serialized_into_queue_jobs(): void
    {
        $this->expectException(LogicException::class);
        serialize($this->request());
    }

    public function test_invalid_request_and_channel_are_rejected(): void
    {
        try {
            $this->request('ftp');
            $this->fail('Unsupported request channel was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        try {
            new OutboundProviderRequest((string) Str::ulid(), 'sms', '+10000000000', '');
            $this->fail('Empty outbound body was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        new FakeOutboundProviderAdapter('ftp');
    }

    public function test_transport_results_are_strictly_normalized_and_never_delivered(): void
    {
        $this->assertSame('accepted', (new OutboundProviderResult(
            ProviderAttemptOutcome::Accepted, 202, 'opaque-1'
        ))->deliveryState());
        $this->assertSame('rejected', (new OutboundProviderResult(
            ProviderAttemptOutcome::Rejected, 400
        ))->deliveryState());
        $this->assertSame('review_required', (new OutboundProviderResult(
            ProviderAttemptOutcome::Retryable, 503
        ))->deliveryState());
        $this->assertSame('review_required', (new OutboundProviderResult(
            ProviderAttemptOutcome::Ambiguous
        ))->deliveryState());
    }

    public function test_result_rejects_pii_provider_reference_and_invalid_http_status(): void
    {
        try {
            new OutboundProviderResult(
                ProviderAttemptOutcome::Accepted, 200, 'person@example.test'
            );
            $this->fail('Potentially identifying provider reference was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        try {
            new OutboundProviderResult(
                ProviderAttemptOutcome::Rejected, 400, 'opaque-1'
            );
            $this->fail('A nonaccepted result carried a provider reference.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        new OutboundProviderResult(ProviderAttemptOutcome::Accepted, 700);
    }
}
