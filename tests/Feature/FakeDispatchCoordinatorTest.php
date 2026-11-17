<?php

namespace Tests\Feature;

use App\Jobs\StageDeliveryJob;
use App\Services\FakeDispatchCoordinator;
use App\Services\FakeSignedCallbackReceipt;
use App\Services\MessagePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FakeDispatchCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /** @return array{client: int, provider: int, message: string, delivery: string, secret: string} */
    private function fixture(string $slug): array
    {
        $now = now()->utc();
        $client = DB::table('clients')->insertGetId([
            'slug' => $slug, 'name' => $slug, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $token = 'relay_'.bin2hex(random_bytes(32));
        $key = DB::table('api_keys')->insertGetId([
            'client_id' => $client, 'label' => 'Fake dispatch only',
            'key_prefix' => substr($token, 0, 16),
            'key_hash' => hash('sha256', $token),
            'scopes' => json_encode(['messages.publish'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        $recipient = $slug.'@example.test';
        $target = DB::table('delivery_targets')->insertGetId([
            'client_id' => $client, 'channel' => 'email',
            'address_ciphertext' => Crypt::encryptString($recipient),
            'address_hmac' => hash('sha256', $recipient),
            'enabled' => true, 'created_at' => $now,
        ]);

        $secret = bin2hex(random_bytes(32));
        $provider = DB::table('provider_accounts')->insertGetId([
            'client_id' => $client,
            'adapter_code' => 'fake-only',
            'channel' => 'email',
            'display_name' => 'Fake integration provider',
            'credentials_ciphertext' => Crypt::encryptString(json_encode([
                'callback_secret' => $secret,
            ], JSON_THROW_ON_ERROR)),
            'enabled' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('client_provider_routes')->insert([
            'client_id' => $client,
            'provider_account_id' => $provider,
            'channel' => 'email', 'priority' => 1,
            'enabled' => true, 'created_at' => $now,
        ]);

        $result = app(MessagePublisher::class)->publish(
            $client, $key, 'fake-dispatch-'.bin2hex(random_bytes(16)),
            [$target], ['event' => 'fake-only', 'sensitive' => 'redact-in-logs'],
        );
        $delivery = $result['delivery_ids'][0];
        $message = $result['message_id'];

        (new StageDeliveryJob($delivery))->handle();

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery, 'state' => 'awaiting_provider',
        ]);

        return compact('client', 'provider', 'message', 'delivery', 'secret');
    }

    public function test_fake_dispatch_stores_accepted_attempt_before_separately_signed_receipt(): void
    {
        $f = $this->fixture('fake-full-path');
        $coordinator = app(FakeDispatchCoordinator::class);
        $result = $coordinator->dispatch($f['client'], $f['delivery'], $f['provider']);

        $this->assertIsArray($result);
        $this->assertIsInt($result['attempt_id']);
        $this->assertStringStartsWith('fake-', $result['provider_ref']);
        $this->assertDatabaseHas('delivery_attempts', [
            'id' => $result['attempt_id'],
            'provider_account_id' => $f['provider'],
            'outcome' => 'accepted',
            'provider_ref' => $result['provider_ref'],
        ]);
        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'accepted', 'attempt_count' => 1,
            'lease_token' => null,
        ]);
        $this->assertDatabaseCount('callback_receipts', 0);

        $timestamp = (string) time();
        $body = json_encode([
            'event_id' => 'event-'.bin2hex(random_bytes(12)),
            'provider_ref' => $result['provider_ref'],
            'status' => 'delivered',
        ], JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256',
            $f['provider']."\n".$timestamp."\n".$body, $f['secret']);

        $this->assertTrue(app(FakeSignedCallbackReceipt::class)
            ->receive($f['provider'], $timestamp, $body, $signature));
        $this->assertDatabaseHas('deliveries', ['id' => $f['delivery'], 'state' => 'delivered']);
        $this->assertDatabaseCount('callback_receipts', 1);
        $this->assertDatabaseCount('delivery_attempts', 1);
    }

    public function test_second_fake_dispatch_never_creates_another_attempt(): void
    {
        $f = $this->fixture('fake-only-once');
        $coordinator = app(FakeDispatchCoordinator::class);

        $this->assertIsArray($coordinator->dispatch($f['client'], $f['delivery'], $f['provider']));
        $this->assertNull($coordinator->dispatch($f['client'], $f['delivery'], $f['provider']));
        $this->assertDatabaseCount('delivery_attempts', 1);
        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'accepted', 'attempt_count' => 1,
        ]);
    }

    public function test_cross_client_cannot_dispatch_a_delivery(): void
    {
        $one = $this->fixture('fake-owner-one');
        $other = $this->fixture('fake-owner-other');
        $coordinator = app(FakeDispatchCoordinator::class);

        $this->assertNull($coordinator->dispatch($other['client'], $one['delivery'], $one['provider']));
        $this->assertNull($coordinator->dispatch($one['client'], $one['delivery'], $other['provider']));
        $this->assertDatabaseCount('delivery_attempts', 0);
        $this->assertDatabaseHas('deliveries', ['id' => $one['delivery'], 'state' => 'awaiting_provider']);
    }

    public function test_disabled_route_or_non_fake_provider_fails_before_leasing(): void
    {
        $f = $this->fixture('fake-invalid-route');
        $coordinator = app(FakeDispatchCoordinator::class);

        DB::table('client_provider_routes')->where('client_id', $f['client'])->update(['enabled' => false]);
        $this->assertNull($coordinator->dispatch($f['client'], $f['delivery'], $f['provider']));

        DB::table('client_provider_routes')->where('client_id', $f['client'])->update(['enabled' => true]);
        DB::table('provider_accounts')->where('id', $f['provider'])->update(['adapter_code' => 'smtp-real']);
        $this->assertNull($coordinator->dispatch($f['client'], $f['delivery'], $f['provider']));

        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'awaiting_provider', 'lease_token' => null,
        ]);
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_invalid_fake_credentials_cannot_be_dispatched(): void
    {
        $f = $this->fixture('fake-bad-secret');
        DB::table('provider_accounts')->where('id', $f['provider'])
            ->update(['credentials_ciphertext' => Crypt::encryptString('not-json')]);

        $this->assertNull(app(FakeDispatchCoordinator::class)
            ->dispatch($f['client'], $f['delivery'], $f['provider']));
        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'awaiting_provider', 'lease_token' => null,
        ]);
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_expired_message_is_never_fake_dispatched(): void
    {
        $f = $this->fixture('fake-expired-message');
        DB::table('messages')->where('id', $f['message'])
            ->update(['expires_at' => now()->utc()->subSecond()]);

        $this->assertNull(app(FakeDispatchCoordinator::class)
            ->dispatch($f['client'], $f['delivery'], $f['provider']));
        $this->assertDatabaseHas('deliveries', ['id' => $f['delivery'], 'state' => 'expired']);
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_production_environment_rejects_fake_dispatch_without_claim(): void
    {
        $f = $this->fixture('fake-production-guard');
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->assertNull(app(FakeDispatchCoordinator::class)
                ->dispatch($f['client'], $f['delivery'], $f['provider']));
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }

        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'awaiting_provider', 'lease_token' => null,
        ]);
        $this->assertDatabaseCount('delivery_attempts', 0);
    }
}
