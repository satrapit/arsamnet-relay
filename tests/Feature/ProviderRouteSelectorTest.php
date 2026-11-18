<?php

namespace Tests\Feature;

use App\Services\ProviderRouteSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderRouteSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /** @return array{client: int, target: int, message: string, delivery: string, provider: int} */
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
        $message = (string) Str::ulid();
        DB::table('messages')->insert([
            'id' => $message, 'client_id' => $client,
            'kind' => 'direct', 'priority' => 'normal',
            'payload_ciphertext' => Crypt::encryptString('{"test":true}'),
            'payload_hash' => hash('sha256', $slug),
            'expires_at' => $now->copy()->addHour(), 'created_at' => $now,
        ]);
        $delivery = (string) Str::ulid();
        DB::table('deliveries')->insert([
            'id' => $delivery, 'client_id' => $client,
            'message_id' => $message, 'target_id' => $target,
            'state' => 'awaiting_provider', 'attempt_count' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $provider = $this->addProvider($client, 20);

        return compact('client', 'target', 'message', 'delivery', 'provider');
    }

    private function addProvider(
        int $clientId,
        int $priority,
        string $channel = 'email',
        bool $enabled = true,
        ?int $ownerId = null,
    ): int {
        $now = now()->utc();
        $providerId = DB::table('provider_accounts')->insertGetId([
            'client_id' => $ownerId ?? $clientId,
            'adapter_code' => 'fake-only',
            'channel' => $channel,
            'display_name' => 'Safe routing fixture',
            'credentials_ciphertext' => Crypt::encryptString('not-used-for-selection'),
            'enabled' => $enabled,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('client_provider_routes')->insert([
            'client_id' => $clientId,
            'provider_account_id' => $providerId,
            'channel' => $channel,
            'priority' => $priority,
            'enabled' => true,
            'created_at' => $now,
        ]);

        return $providerId;
    }

    public function test_lowest_priority_number_wins_without_any_side_effect(): void
    {
        $f = $this->fixture('route-priority');
        $preferred = $this->addProvider($f['client'], 5);
        $selector = app(ProviderRouteSelector::class);

        $this->assertSame($preferred, $selector->select($f['client'], $f['delivery']));
        $this->assertSame($preferred, $selector->select($f['client'], $f['delivery']));
        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'awaiting_provider', 'attempt_count' => 0,
        ]);
        $this->assertDatabaseCount('delivery_attempts', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_disabled_route_and_account_are_not_selected(): void
    {
        $f = $this->fixture('route-disabled');
        $preferred = $this->addProvider($f['client'], 5);
        $selector = app(ProviderRouteSelector::class);
        DB::table('client_provider_routes')->where('provider_account_id', $preferred)
            ->update(['enabled' => false]);

        $this->assertSame($f['provider'], $selector->select($f['client'], $f['delivery']));

        DB::table('client_provider_routes')->where('provider_account_id', $preferred)
            ->update(['enabled' => true]);
        DB::table('provider_accounts')->where('id', $preferred)->update(['enabled' => false]);

        $this->assertSame($f['provider'], $selector->select($f['client'], $f['delivery']));
    }

    public function test_cross_tenant_private_account_is_never_selected_even_if_routed(): void
    {
        $one = $this->fixture('route-owner-one');
        $other = $this->fixture('route-owner-other');

        DB::table('client_provider_routes')->where('client_id', $one['client'])->delete();
        DB::table('client_provider_routes')->insert([
            'client_id' => $one['client'],
            'provider_account_id' => $other['provider'],
            'channel' => 'email', 'priority' => 1,
            'enabled' => true, 'created_at' => now(),
        ]);

        $this->assertNull(app(ProviderRouteSelector::class)
            ->select($one['client'], $one['delivery']));
        $this->assertNull(app(ProviderRouteSelector::class)
            ->select($other['client'], $one['delivery']));
    }

    public function test_platform_shared_provider_is_selectable_with_explicit_route(): void
    {
        $f = $this->fixture('route-shared');
        DB::table('provider_accounts')->where('id', $f['provider'])
            ->update(['client_id' => null]);

        $this->assertSame($f['provider'], app(ProviderRouteSelector::class)
            ->select($f['client'], $f['delivery']));
    }

    public function test_suspended_client_or_disabled_target_cannot_select_a_route(): void
    {
        $f = $this->fixture('route-suspended');
        $selector = app(ProviderRouteSelector::class);

        DB::table('clients')->where('id', $f['client'])->update(['status' => 'suspended']);
        $this->assertNull($selector->select($f['client'], $f['delivery']));

        DB::table('clients')->where('id', $f['client'])->update(['status' => 'active']);
        DB::table('delivery_targets')->where('id', $f['target'])->update(['enabled' => false]);
        $this->assertNull($selector->select($f['client'], $f['delivery']));
    }

    public function test_expired_message_and_nonstaged_delivery_are_never_selected(): void
    {
        $f = $this->fixture('route-expired');
        $selector = app(ProviderRouteSelector::class);

        DB::table('messages')->where('id', $f['message'])
            ->update(['expires_at' => now()->utc()->subSecond()]);
        $this->assertNull($selector->select($f['client'], $f['delivery']));

        DB::table('messages')->where('id', $f['message'])
            ->update(['expires_at' => now()->utc()->addHour()]);
        DB::table('deliveries')->where('id', $f['delivery'])
            ->update(['state' => 'review_required']);
        $this->assertNull($selector->select($f['client'], $f['delivery']));
    }

    public function test_started_attempt_and_existing_lease_prevent_failover_selection(): void
    {
        $f = $this->fixture('route-no-failover');
        $selector = app(ProviderRouteSelector::class);

        DB::table('deliveries')->where('id', $f['delivery'])
            ->update(['attempt_count' => 1]);
        $this->assertNull($selector->select($f['client'], $f['delivery']));

        DB::table('deliveries')->where('id', $f['delivery'])
            ->update(['attempt_count' => 0, 'lease_token' => (string) Str::uuid()]);
        $this->assertNull($selector->select($f['client'], $f['delivery']));
    }

    public function test_channel_mismatch_is_never_selected(): void
    {
        $f = $this->fixture('route-channel');
        DB::table('client_provider_routes')->where('client_id', $f['client'])->delete();
        $this->addProvider($f['client'], 3, 'sms');

        $this->assertNull(app(ProviderRouteSelector::class)
            ->select($f['client'], $f['delivery']));
    }

    public function test_missing_client_or_delivery_has_no_provider_selection(): void
    {
        $f = $this->fixture('route-invalid-ids');
        $selector = app(ProviderRouteSelector::class);

        $this->assertNull($selector->select(0, $f['delivery']));
        $this->assertNull($selector->select($f['client'] + 1000, $f['delivery']));
        $this->assertNull($selector->select($f['client'], 'not-a-delivery'));
    }

    public function test_future_scheduled_message_has_no_provider_until_due(): void
    {
        $f = $this->fixture('route-scheduled');
        $selector = app(ProviderRouteSelector::class);
        DB::table('messages')->where('id', $f['message'])
            ->update(['scheduled_at' => now()->utc()->addMinutes(5)]);
        $this->assertNull($selector->select($f['client'], $f['delivery']));

        DB::table('messages')->where('id', $f['message'])
            ->update(['scheduled_at' => now()->utc()->subMinute()]);
        $this->assertSame($f['provider'], $selector->select($f['client'], $f['delivery']));
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_fake_provider_is_not_selected_in_production(): void
    {
        $f = $this->fixture('route-production-fake');
        $selector = app(ProviderRouteSelector::class);

        app()->detectEnvironment(fn (): string => 'production');
        try {
            $this->assertNull($selector->select($f['client'], $f['delivery']));
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }

        $this->assertSame($f['provider'], $selector->select($f['client'], $f['delivery']));
    }
}
