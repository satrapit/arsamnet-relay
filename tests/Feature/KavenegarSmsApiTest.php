<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class KavenegarSmsApiTest extends TestCase
{
    use RefreshDatabase;

    private const PROVIDER_KEY = 'TEST_KAVENEGAR_KEY_12345678901234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        Feature::globally()->activate('message-publishing');
        Feature::globally()->activate('kavenegar-sending');
        Feature::globally()->activate('kavenegar-hourly-limit', 100);
        Http::preventStrayRequests();
    }

    /** @return array{client:int,token:string,provider:int} */
    private function fixture(string $slug, bool $providerEnabled = true, array $scopes = ['messages.publish', 'messages.read']): array
    {
        $now = now()->utc();
        $client = DB::table('clients')->insertGetId([
            'slug' => $slug, 'name' => $slug, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $token = 'relay_'.bin2hex(random_bytes(32));
        DB::table('api_keys')->insert([
            'client_id' => $client, 'label' => 'SMS feature test key',
            'key_prefix' => substr($token, 0, 16), 'key_hash' => hash('sha256', $token),
            'scopes' => json_encode($scopes, JSON_THROW_ON_ERROR), 'created_at' => $now,
        ]);
        $provider = DB::table('provider_accounts')->insertGetId([
            'client_id' => $client, 'adapter_code' => 'kavenegar', 'channel' => 'sms',
            'display_name' => 'Kavenegar fixture',
            'credentials_ciphertext' => Crypt::encryptString(json_encode([
                'api_key' => self::PROVIDER_KEY, 'sender' => '10001234',
            ], JSON_THROW_ON_ERROR)),
            'enabled' => $providerEnabled,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('client_provider_routes')->insert([
            'client_id' => $client, 'provider_account_id' => $provider, 'channel' => 'sms',
            'priority' => 1, 'enabled' => $providerEnabled, 'created_at' => $now,
        ]);

        return compact('client', 'token', 'provider');
    }

    private function goodResponse(array $ids): void
    {
        Http::fake(['*' => Http::response([
            'return' => ['status' => 200, 'message' => 'OK'],
            'entries' => array_map(fn ($id): array => ['messageid' => $id], $ids),
        ], 200)]);
    }

    private function request(string $token, string $idempotency, string $url, array $data)
    {
        return $this->withToken($token)
            ->withHeader('Idempotency-Key', $idempotency)
            ->postJson($url, $data);
    }

    public function test_network_send_is_disabled_by_default_without_any_database_write(): void
    {
        $f = $this->fixture('sms-off');
        Feature::globally()->deactivate('kavenegar-sending');
        Http::fake();

        $this->request($f['token'], 'sms-disabled-request-0001', '/api/v1/sms/send', [
            'receptor' => '09123456789', 'message' => 'Hello',
        ])->assertStatus(503);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('delivery_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_single_sms_is_persisted_before_one_provider_call_and_not_marked_delivered(): void
    {
        $f = $this->fixture('sms-single');
        $this->goodResponse([123456]);

        $response = $this->request($f['token'], 'sms-single-request-0002', '/api/v1/sms/send', [
            'receptor' => '+989123456789', 'message' => 'Sensitive SMS content',
        ])->assertStatus(202)->assertJsonPath('data.state', 'accepted')
            ->assertJsonPath('data.replayed', false);

        $this->assertDatabaseHas('delivery_attempts', [
            'provider_account_id' => $f['provider'], 'outcome' => 'accepted',
            'provider_ref' => 'kv-123456',
        ]);
        $this->assertDatabaseHas('deliveries', [
            'id' => $response->json('data.delivery_ids.0'), 'state' => 'accepted',
            'attempt_count' => 1,
        ]);

        $msg = DB::table('messages')->first();
        $this->assertStringNotContainsString('Sensitive SMS content', $msg->payload_ciphertext);
        $this->assertStringNotContainsString('09123456789', $msg->payload_ciphertext);
        $this->assertStringNotContainsString('Sensitive SMS content', DB::table('jobs')->first()->payload);
        $this->assertStringNotContainsString('09123456789', DB::table('jobs')->first()->payload);
        $this->assertSame('09123456789', Crypt::decryptString(DB::table('delivery_targets')->first()->address_ciphertext));
        Http::assertSentCount(1);
    }

    public function test_idempotent_replay_never_sends_to_provider_again(): void
    {
        $f = $this->fixture('sms-replay');
        $this->goodResponse([777]);
        $body = ['receptor' => '09123456789', 'message' => 'One time send'];

        $first = $this->request($f['token'], 'sms-replay-request-0003', '/api/v1/sms/send', $body)
            ->assertStatus(202);
        $this->request($f['token'], 'sms-replay-request-0003', '/api/v1/sms/send', $body)
            ->assertOk()->assertJsonPath('data.replayed', true)
            ->assertJsonPath('data.message_id', $first->json('data.message_id'));

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('delivery_attempts', 1);
        Http::assertSentCount(1);

        $this->request($f['token'], 'sms-replay-request-0003', '/api/v1/sms/send', [
            'receptor' => '09123456789', 'message' => 'Changed message',
        ])->assertStatus(409);
        Http::assertSentCount(1);
    }

    public function test_sendarray_one_provider_call_maps_two_receipts_to_two_deliveries(): void
    {
        $f = $this->fixture('sms-bulk');
        $this->goodResponse([11111, 22222]);
        $resp = $this->request($f['token'], 'sms-bulk-request-0004', '/api/v1/sms/bulk', [
            'items' => [
                ['receptor' => '09123456789', 'message' => 'Message A'],
                ['receptor' => '09123456780', 'message' => 'Message B'],
            ],
        ])->assertStatus(202)->assertJsonPath('data.state', 'accepted');

        $this->assertCount(2, $resp->json('data.delivery_ids'));
        $this->assertDatabaseCount('delivery_attempts', 2);
        $this->assertDatabaseCount('deliveries', 2);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/sms/sendarray.json'));
    }

    public function test_otp_uses_template_and_token_without_returning_or_logging_code(): void
    {
        $f = $this->fixture('sms-otp');
        $this->goodResponse([333]);
        $resp = $this->request($f['token'], 'sms-otp-request-0005', '/api/v1/sms/otp', [
            'receptor' => '09123456789', 'template' => 'LoginCode', 'token' => '912345',
        ])->assertStatus(202)->assertJsonPath('data.state', 'accepted');

        $this->assertStringNotContainsString('912345', $resp->getContent());
        $cipher = DB::table('messages')->first()->payload_ciphertext;
        $this->assertStringNotContainsString('912345', $cipher);
        $this->assertStringNotContainsString('912345', DB::table('jobs')->first()->payload);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/verify/lookup.json')
            && $request['token'] === '912345' && $request['template'] === 'LoginCode');

        $this->request($f['token'], 'sms-otp-request-0006', '/api/v1/sms/otp', [
            'receptor' => '09123456789', 'template' => 'LoginCode', 'token' => '912346',
        ])->assertStatus(422);
        Http::assertSentCount(1);
    }

    public function test_disabled_provider_and_foreign_tenant_cannot_send_sms(): void
    {
        $off = $this->fixture('sms-provider-off', false);
        $other = $this->fixture('sms-provider-other', true);
        Http::fake();

        $this->request($off['token'], 'sms-provider-disabled-0007', '/api/v1/sms/send', [
            'receptor' => '09123456789', 'message' => 'No delivery',
        ])->assertStatus(422);

        // A different tenant's provider cannot rescue a disabled tenant.
        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();

        $this->request($other['token'], 'sms-other-tenant-0008', '/api/v1/sms/send', [
            'receptor' => '09123456789', 'message' => 'Valid tenant',
        ])->assertStatus(202);
    }

    public function test_provider_http_failure_is_quarantined_and_never_retried(): void
    {
        $f = $this->fixture('sms-provider-error');
        Http::fake(['*' => Http::response('Gateway timeout', 504)]);

        $response = $this->request($f['token'], 'sms-http-error-0009', '/api/v1/sms/send', [
            'receptor' => '09123456789', 'message' => 'No blind retries',
        ])->assertStatus(202)->assertJsonPath('data.state', 'review_required');

        $this->assertDatabaseHas('deliveries', [
            'id' => $response->json('data.delivery_ids.0'), 'state' => 'review_required',
            'last_error_code' => 'PROVIDER_AMBIGUOUS',
        ]);
        $this->assertDatabaseCount('delivery_attempts', 1);
        Http::assertSentCount(1);
    }

    public function test_api_scopes_and_validation_block_invalid_recipients(): void
    {
        $f = $this->fixture('sms-scope', true, ['messages.read']);
        Http::fake();

        $this->request($f['token'], 'sms-bad-scope-00010', '/api/v1/sms/send', [
            'receptor' => '09123456789', 'message' => 'Hello',
        ])->assertForbidden();

        $good = $this->fixture('sms-validate', true, ['messages.publish']);
        $this->request($good['token'], 'sms-invalid-phone-0011', '/api/v1/sms/send', [
            'receptor' => '001234567', 'message' => 'Hello',
        ])->assertStatus(422);

        $this->request($good['token'], 'sms-duplicate-bulk-0012', '/api/v1/sms/bulk', [
            'items' => [
                ['receptor' => '09123456789', 'message' => 'A'],
                ['receptor' => '+989123456789', 'message' => 'B'],
            ],
        ])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_status_endpoint_is_scoped_and_never_exposes_sms_body_phone_or_otp(): void
    {
        $f = $this->fixture('sms-status');
        $other = $this->fixture('sms-status-other');
        $this->goodResponse([888]);
        $send = $this->request($f['token'], 'sms-status-request-0013', '/api/v1/sms/send', [
            'receptor' => '09123456789', 'message' => 'Sensitive status body',
        ])->assertStatus(202);
        $id = $send->json('data.message_id');

        $status = $this->withToken($f['token'])->getJson('/api/v1/sms/messages/'.$id)
            ->assertOk()->assertJsonPath('data.message_id', $id);

        $this->assertStringNotContainsString('09123456789', $status->getContent());
        $this->assertStringNotContainsString('Sensitive status body', $status->getContent());
        $this->withToken($other['token'])
            ->getJson('/api/v1/sms/messages/'.$id)->assertNotFound();
    }

    public function test_client_hourly_quota_fails_closed_before_any_second_send(): void
    {
        $f = $this->fixture('sms-hourly-limit');
        Feature::globally()->activate('kavenegar-hourly-limit', 1);
        $this->goodResponse([999]);

        $body = ['receptor' => '09123456789', 'message' => 'First SMS'];
        $this->request($f['token'], 'sms-quota-request-0014', '/api/v1/sms/send', $body)
            ->assertStatus(202);

        $this->request($f['token'], 'sms-quota-request-0015', '/api/v1/sms/send', [
            'receptor' => '09123456780', 'message' => 'Second SMS',
        ])->assertStatus(422);

        // Idempotent replay bypasses the new-send quota but never resends.
        $this->request($f['token'], 'sms-quota-request-0014', '/api/v1/sms/send', $body)
            ->assertOk()->assertJsonPath('data.replayed', true);

        Http::assertSentCount(1);
    }
}
