<?php

namespace Tests\Feature;

use App\Services\FakeSignedCallbackReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FakeSignedCallbackReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /** @return array{client: int, provider: int, delivery: string, attempt: int, secret: string, ref: string} */
    private function fixture(string $slug): array
    {
        $now = now()->utc();
        $client = DB::table('clients')->insertGetId([
            'slug' => $slug, 'name' => $slug, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $target = DB::table('delivery_targets')->insertGetId([
            'client_id' => $client, 'channel' => 'email',
            'address_ciphertext' => Crypt::encryptString($slug.'@example.test'),
            'address_hmac' => hash('sha256', $slug.'@example.test'),
            'enabled' => true, 'created_at' => $now,
        ]);
        $messageId = (string) Str::ulid();
        DB::table('messages')->insert([
            'id' => $messageId, 'client_id' => $client, 'kind' => 'direct',
            'priority' => 'normal', 'payload_ciphertext' => Crypt::encryptString('{"demo":true}'),
            'payload_hash' => hash('sha256', $slug), 'created_at' => $now,
        ]);
        $delivery = (string) Str::ulid();
        DB::table('deliveries')->insert([
            'id' => $delivery, 'client_id' => $client, 'message_id' => $messageId,
            'target_id' => $target, 'state' => 'accepted', 'attempt_count' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $secret = bin2hex(random_bytes(32));
        $provider = DB::table('provider_accounts')->insertGetId([
            'client_id' => $client, 'adapter_code' => 'fake-only',
            'channel' => 'email', 'display_name' => 'Test fake account',
            'credentials_ciphertext' => Crypt::encryptString(json_encode([
                'callback_secret' => $secret,
            ], JSON_THROW_ON_ERROR)),
            'enabled' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('client_provider_routes')->insert([
            'client_id' => $client, 'provider_account_id' => $provider,
            'channel' => 'email', 'priority' => 1, 'enabled' => true, 'created_at' => $now,
        ]);
        $ref = 'opaque-'.bin2hex(random_bytes(10));
        $attempt = DB::table('delivery_attempts')->insertGetId([
            'delivery_id' => $delivery, 'provider_account_id' => $provider,
            'attempt_no' => 1, 'outcome' => 'accepted',
            'provider_ref' => $ref, 'created_at' => $now,
        ]);

        return compact('client', 'provider', 'delivery', 'attempt', 'secret', 'ref');
    }

    /** @return array{body: string, signature: string, timestamp: string, event_id: string} */
    private function signed(array $fixture, ?string $eventId = null, ?string $timestamp = null, ?string $ref = null): array
    {
        $eventId ??= 'event-'.bin2hex(random_bytes(12));
        $timestamp ??= (string) time();
        $body = json_encode([
            'event_id' => $eventId,
            'provider_ref' => $ref ?? $fixture['ref'],
            'status' => 'delivered',
        ], JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256',
            $fixture['provider']."\n".$timestamp."\n".$body, $fixture['secret']);

        return ['body' => $body, 'signature' => $signature, 'timestamp' => $timestamp, 'event_id' => $eventId];
    }

    private function deliver(array $fixture, array $callback): bool
    {
        return app(FakeSignedCallbackReceipt::class)->receive(
            $fixture['provider'], $callback['timestamp'],
            $callback['body'], $callback['signature']
        );
    }

    public function test_authenticated_fake_receipt_records_hmac_and_proves_delivery(): void
    {
        $f = $this->fixture('signed-ok');
        $callback = $this->signed($f);

        $this->assertTrue($this->deliver($f, $callback));
        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'delivered', 'attempt_count' => 1,
        ]);
        $receipt = DB::table('callback_receipts')->first();
        $this->assertSame($f['provider'], (int) $receipt->provider_account_id);
        $this->assertSame($f['attempt'], (int) $receipt->delivery_attempt_id);
        $this->assertSame('delivered', $receipt->status);
        $this->assertSame(64, strlen($receipt->event_id_hmac));
        $this->assertNotSame($callback['event_id'], $receipt->event_id_hmac);
        $this->assertDatabaseCount('callback_receipts', 1);
        $this->assertDatabaseCount('delivery_attempts', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_signed_event_replay_and_second_event_cannot_deliver_twice(): void
    {
        $f = $this->fixture('signed-replay');
        $callback = $this->signed($f);

        $this->assertTrue($this->deliver($f, $callback));
        $this->assertFalse($this->deliver($f, $callback));
        $this->assertFalse($this->deliver($f, $this->signed($f)));
        $this->assertDatabaseCount('callback_receipts', 1);
        $this->assertDatabaseHas('deliveries', ['id' => $f['delivery'], 'state' => 'delivered']);
    }

    public function test_invalid_signature_and_altered_signed_payload_are_denied(): void
    {
        $f = $this->fixture('signed-invalid');
        $callback = $this->signed($f);

        $this->assertFalse(app(FakeSignedCallbackReceipt::class)->receive(
            $f['provider'], $callback['timestamp'], $callback['body'],
            'sha256='.str_repeat('a', 64)
        ));
        $this->assertFalse(app(FakeSignedCallbackReceipt::class)->receive(
            $f['provider'], $callback['timestamp'],
            str_replace('delivered', 'rejected', $callback['body']), $callback['signature']
        ));
        $this->assertDatabaseCount('callback_receipts', 0);
        $this->assertDatabaseHas('deliveries', ['id' => $f['delivery'], 'state' => 'accepted']);
    }

    public function test_stale_or_future_timestamp_fails_even_when_correctly_signed(): void
    {
        $f = $this->fixture('signed-clock');

        $this->assertFalse($this->deliver($f, $this->signed($f, null, (string) (time() - 3600))));
        $this->assertFalse($this->deliver($f, $this->signed($f, null, (string) (time() + 3600))));
        $this->assertDatabaseCount('callback_receipts', 0);
    }

    public function test_unmatched_or_wrong_provider_reference_cannot_mark_delivered(): void
    {
        $f = $this->fixture('signed-unknown-ref');
        $callback = $this->signed($f, null, null, 'opaque-not-a-real-attempt');

        $this->assertFalse($this->deliver($f, $callback));
        $this->assertDatabaseHas('deliveries', ['id' => $f['delivery'], 'state' => 'accepted']);
        $this->assertDatabaseCount('callback_receipts', 0);
    }

    public function test_cross_tenant_provider_account_is_rejected_despite_a_bad_route(): void
    {
        $one = $this->fixture('signed-owner-one');
        $two = $this->fixture('signed-owner-two');
        $ref = 'opaque-cross-'.bin2hex(random_bytes(8));

        DB::table('delivery_attempts')->where('id', $two['attempt'])->update([
            'provider_account_id' => $one['provider'], 'provider_ref' => $ref,
        ]);
        DB::table('client_provider_routes')->insert([
            'client_id' => $two['client'], 'provider_account_id' => $one['provider'],
            'channel' => 'email', 'priority' => 2, 'enabled' => true,
            'created_at' => now(),
        ]);

        $this->assertFalse($this->deliver($one, $this->signed($one, null, null, $ref)));
        $this->assertDatabaseHas('deliveries', ['id' => $two['delivery'], 'state' => 'accepted']);
        $this->assertDatabaseCount('callback_receipts', 0);
    }

    public function test_disabled_provider_route_and_wrong_adapter_are_denied(): void
    {
        $f = $this->fixture('signed-route-disabled');
        $callback = $this->signed($f);
        DB::table('client_provider_routes')->where('client_id', $f['client'])
            ->update(['enabled' => false]);
        $this->assertFalse($this->deliver($f, $callback));

        DB::table('client_provider_routes')->where('client_id', $f['client'])
            ->update(['enabled' => true]);
        DB::table('provider_accounts')->where('id', $f['provider'])
            ->update(['adapter_code' => 'smtp-real']);
        $this->assertFalse($this->deliver($f, $callback));
        $this->assertDatabaseCount('callback_receipts', 0);
    }

    public function test_nonaccepted_attempt_and_unsupported_event_are_denied(): void
    {
        $f = $this->fixture('signed-not-accepted');
        DB::table('delivery_attempts')->where('id', $f['attempt'])
            ->update(['outcome' => 'ambiguous']);
        $this->assertFalse($this->deliver($f, $this->signed($f)));
        $this->assertDatabaseCount('callback_receipts', 0);
    }

    public function test_fake_callback_logic_refuses_production_even_with_valid_signature(): void
    {
        $f = $this->fixture('signed-production-guard');
        $callback = $this->signed($f);
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->assertFalse($this->deliver($f, $callback));
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }

        $this->assertDatabaseCount('callback_receipts', 0);
    }
}
