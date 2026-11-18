<?php

namespace Tests\Unit;

use App\Providers\Adapters\KavenegarSmsAdapter;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class KavenegarStatusAdapterTest extends TestCase
{
    private const KEY = 'TEST_KAVENEGAR_KEY_12345678901234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('pennant.default', 'array');
        Feature::globally()->deactivate('kavenegar-sending');
        Feature::globally()->activate('provider-status-queries');
        Http::preventStrayRequests();
    }

    private function adapter(): KavenegarSmsAdapter
    {
        return new KavenegarSmsAdapter(self::KEY, '10001234');
    }

    /** @return array<string,mixed> */
    private function providerStatusResponse(int $code, int $id = 987654): array
    {
        return [
            'return' => ['status' => 200, 'message' => 'Secret diagnostic'],
            'entries' => [[
                'messageid' => $id, 'status' => $code,
                'receptor' => '09123456789', 'statustext' => 'Private status',
            ]],
        ];
    }

    public function test_status_uses_independent_gate_and_never_sends_sms(): void
    {
        Http::fake(['*' => Http::response($this->providerStatusResponse(10), 200)]);
        $result = $this->adapter()->queryStatus('987654');

        $this->assertSame(['state' => 'provider_reports_delivered', 'provider_code' => 10], $result);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.kavenegar.com/v1/'.self::KEY.'/sms/status.json'
            && $request['messageid'] === '987654'
            && ! isset($request['receptor'])
            && ! isset($request['message'])
        );
        Http::assertSentCount(1);
        $this->assertStringNotContainsString('09123456789', json_encode($result));
        $this->assertStringNotContainsString('Secret diagnostic', json_encode($result));
    }

    public function test_nonterminal_status_never_claims_delivered(): void
    {
        Http::fake(['*' => Http::response($this->providerStatusResponse(4), 200)]);
        $this->assertSame(['state' => 'not_confirmed', 'provider_code' => 4],
            $this->adapter()->queryStatus('987654'));
    }

    public function test_mismatched_id_and_malformed_response_are_unknown(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->providerStatusResponse(10, 111222), 200)
            ->push(['return' => ['status' => 200], 'entries' => []], 200)]);
        $this->assertSame(['state' => 'unknown', 'provider_code' => null],
            $this->adapter()->queryStatus('987654'));
        $this->assertSame(['state' => 'unknown', 'provider_code' => null],
            $this->adapter()->queryStatus('987654'));
    }

    public function test_status_gate_and_bad_message_id_fail_without_network(): void
    {
        Feature::globally()->deactivate('provider-status-queries');
        Http::fake();
        try {
            $this->adapter()->queryStatus('987654');
            $this->fail('Disabled status query was permitted.');
        } catch (\LogicException) {
            Http::assertNothingSent();
        }

        Feature::globally()->activate('provider-status-queries');
        $this->expectException(InvalidArgumentException::class);
        $this->adapter()->queryStatus('987654/other');
    }
}
