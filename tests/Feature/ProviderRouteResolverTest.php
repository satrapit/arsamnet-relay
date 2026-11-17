<?php

namespace Tests\Feature;

use App\Services\ProviderRouteResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderRouteResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /** @return array{client: int, target: int, message: string, delivery: string} */
    private function fixture(string $slug, string $channel = 'email'): array
    {
        $now = now()->utc();
        $client = DB::table('clients')->insertGetId([
            'slug' => $slug, 'name' => $slug, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $target = DB::table('delivery_targets')->insertGetId([
            'client_id' => $client, 'channel' => $channel,
            'address_ciphertext' => Crypt::encryptString($slug.'@example.test'),
            'address_hmac' => hash('sha256', $slug.'@example.test'),
            'enabled' => true, 'created_at' => $now,
        ]);
        $message = (string) Str::ulid();
        DB::table('messages')->insert([
            'id' => $message, 'client_id' => $client, 'kind' => 'direct',
            'priority' => 'normal',
            'payload_ciphertext' => Crypt::encryptString('{"fake":"test"}'),
            'payload_hash' => hash('sha256', $slug),
            'expires_at' => $now->copy()->addHour(),
            'created_at' => $now,
        ]);
        $delivery = (string) Str::ulid();
        DB::table('deliveries')->insert([
            'id' => $delivery, 'client_id' => $client,
            'message_id' => $message, 'target_id' => $target,
            'state' => 'awaiting_provider', 'attempt_count' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return compact('client', 'target', 'message', 'delivery');
    }

    private function provider(?int $owner, string $channel = 'email', string $adapter = 'fake-only'): int
    {
        return DB::table('provider_accounts')->insertGetId([
            'client_id' => $owner, 'channel' => $channel,
            'adapter_code' => $adapter, 'display_name' => 'Route selection fixture',
            'credentials_ciphertext' => Crypt::encryptString('fake-unread-credentials'),
            'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function route(int $client, int $provider, int $priority, string $channel = 'email'): void
    {
        DB::table('client_provider_routes')->insert([
            'client_id' => $client,
            'provider_account_id' => $provider,
            'channel' => $channel, 'priority' => $priority,
            'enabled' => true, 'created_at' => now(),
        ]);
    }

    public function test_lowest_enabled_priority_wins_for_a_staged_delivery(): void
    {
        $f = $this->fixture('routing-priority');
        $lower = $this->provider($f['client']);
        $higher = $this->provider($f['client']);
        $this->route($f['client'], $lower, 20);
        $this->route($f['client'], $higher, 10);

        $this->assertSame($higher, app(ProviderRouteResolver::class)
            ->resolveForDelivery($f['client'], $f['delivery']));
        $this->assertDatabaseCount('delivery_attempts', 0);
        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'awaiting_provider',
            'attempt_count' => 0, 'lease_token' => null,
        ]);
    }

    public function test_disabled_route_and_provider_are_skipped_only_before_attempt(): void
    {
        $f = $this->fixture('routing-disabled');
        $first = $this->provider($f['client']);
        $second = $this->provider($f['client']);
        $this->route($f['client'], $first, 1);
        $this->route($f['client'], $second, 2);
        $resolver = app(ProviderRouteResolver::class);

        DB::table('client_provider_routes')->where('provider_account_id', $first)->update(['enabled' => false]);
        $this->assertSame($second, $resolver->resolveForDelivery($f['client'], $f['delivery']));
        DB::table('client_provider_routes')->where('provider_account_id', $first)->update(['enabled' => true]);
        DB::table('provider_accounts')->where('id', $first)->update(['enabled' => false]);
        $this->assertSame($second, $resolver->resolveForDelivery($f['client'], $f['delivery']));

        DB::table('provider_accounts')->where('id', $second)->update(['enabled' => false]);
        $this->assertNull($resolver->resolveForDelivery($f['client'], $f['delivery']));
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_route_channel_must_match_both_account_and_target(): void
    {
        $f = $this->fixture('routing-mismatch');
        $sms = $this->provider($f['client'], 'sms');
        $this->route($f['client'], $sms, 1, 'email');

        $this->assertNull(app(ProviderRouteResolver::class)
            ->resolveForDelivery($f['client'], $f['delivery']));
    }

    public function test_misconfigured_cross_tenant_private_provider_is_excluded(): void
    {
        $f = $this->fixture('routing-private-owner');
        $other = $this->fixture('routing-private-other');
        $foreign = $this->provider($other['client']);
        $this->route($f['client'], $foreign, 1);

        $this->assertNull(app(ProviderRouteResolver::class)
            ->resolveForDelivery($f['client'], $f['delivery']));
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_shared_provider_is_available_only_with_explicit_client_route(): void
    {
        $first = $this->fixture('routing-shared-one');
        $other = $this->fixture('routing-shared-other');
        $shared = $this->provider(null);
        $this->route($first['client'], $shared, 1);
        $resolver = app(ProviderRouteResolver::class);

        $this->assertSame($shared, $resolver->resolveForDelivery($first['client'], $first['delivery']));
        $this->assertNull($resolver->resolveForDelivery($other['client'], $other['delivery']));
    }

    public function test_inactive_client_or_disabled_target_blocks_selection(): void
    {
        $f = $this->fixture('routing-client-state');
        $provider = $this->provider($f['client']);
        $this->route($f['client'], $provider, 1);
        $resolver = app(ProviderRouteResolver::class);

        DB::table('clients')->where('id', $f['client'])->update(['status' => 'suspended']);
        $this->assertNull($resolver->resolveForDelivery($f['client'], $f['delivery']));

        DB::table('clients')->where('id', $f['client'])->update(['status' => 'active']);
        DB::table('delivery_targets')->where('id', $f['target'])->update(['enabled' => false]);
        $this->assertNull($resolver->resolveForDelivery($f['client'], $f['delivery']));
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_leased_attempted_or_quarantined_delivery_never_offers_failover(): void
    {
        $f = $this->fixture('routing-no-resend');
        $provider = $this->provider($f['client']);
        $this->route($f['client'], $provider, 1);
        $resolver = app(ProviderRouteResolver::class);

        DB::table('deliveries')->where('id', $f['delivery'])
            ->update(['attempt_count' => 1]);
        $this->assertNull($resolver->resolveForDelivery($f['client'], $f['delivery']));

        DB::table('deliveries')->where('id', $f['delivery'])
            ->update(['attempt_count' => 0, 'state' => 'leased', 'lease_token' => (string) Str::uuid()]);
        $this->assertNull($resolver->resolveForDelivery($f['client'], $f['delivery']));

        DB::table('deliveries')->where('id', $f['delivery'])
            ->update(['state' => 'review_required', 'lease_token' => null]);
        $this->assertNull($resolver->resolveForDelivery($f['client'], $f['delivery']));
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_message_expiration_and_future_schedule_block_route_selection(): void
    {
        $f = $this->fixture('routing-time');
        $provider = $this->provider($f['client']);
        $this->route($f['client'], $provider, 1);
        $resolver = app(ProviderRouteResolver::class);

        DB::table('messages')->where('id', $f['message'])
            ->update(['expires_at' => now()->utc()->subSecond()]);
        $this->assertNull($resolver->resolveForDelivery($f['client'], $f['delivery']));

        DB::table('messages')->where('id', $f['message'])
            ->update([
                'expires_at' => now()->utc()->addHour(),
                'scheduled_at' => now()->utc()->addMinutes(5),
            ]);
        $this->assertNull($resolver->resolveForDelivery($f['client'], $f['delivery']));

        DB::table('messages')->where('id', $f['message'])
            ->update(['scheduled_at' => now()->utc()->subMinute()]);
        $this->assertSame($provider, $resolver->resolveForDelivery($f['client'], $f['delivery']));
    }

    public function test_fake_only_accounts_are_not_eligible_in_production(): void
    {
        $f = $this->fixture('routing-production-fake');
        $provider = $this->provider($f['client']);
        $this->route($f['client'], $provider, 1);
        $resolver = app(ProviderRouteResolver::class);

        app()->detectEnvironment(fn (): string => 'production');
        try {
            $this->assertNull($resolver->resolveForDelivery($f['client'], $f['delivery']));
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }
        $this->assertSame($provider, $resolver->resolveForDelivery($f['client'], $f['delivery']));
    }

    public function test_invalid_ids_and_missing_routes_fail_closed_without_writes(): void
    {
        $f = $this->fixture('routing-no-route');
        $resolver = app(ProviderRouteResolver::class);

        $this->assertNull($resolver->resolveForDelivery(0, $f['delivery']));
        $this->assertNull($resolver->resolveForDelivery($f['client'], 'invalid'));
        $this->assertNull($resolver->resolveForDelivery($f['client'], $f['delivery']));
        $this->assertDatabaseCount('delivery_attempts', 0);
        $this->assertDatabaseCount('jobs', 0);
    }
}
