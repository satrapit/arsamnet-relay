<?php

namespace Tests\Feature;

use App\Services\DeliveryLeaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class DeliveryLeaseManagerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{client: int, target: int, message: string, delivery: string} */
    private function fixture(string $slug, string $state = 'awaiting_provider', int $ttl = 3600): array
    {
        $now = now()->utc();
        $client = DB::table('clients')->insertGetId([
            'slug' => $slug,
            'name' => $slug,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $target = DB::table('delivery_targets')->insertGetId([
            'client_id' => $client,
            'channel' => 'email',
            'address_ciphertext' => Crypt::encryptString($slug.'@example.test'),
            'address_hmac' => hash('sha256', $slug.'@example.test'),
            'enabled' => true,
            'created_at' => $now,
        ]);
        $message = (string) Str::ulid();
        DB::table('messages')->insert([
            'id' => $message,
            'client_id' => $client,
            'kind' => 'direct',
            'priority' => 'normal',
            'payload_ciphertext' => Crypt::encryptString('{"probe":"safe"}'),
            'payload_hash' => hash('sha256', $slug),
            'expires_at' => $now->copy()->addSeconds($ttl),
            'created_at' => $now,
        ]);
        $delivery = (string) Str::ulid();
        DB::table('deliveries')->insert([
            'id' => $delivery,
            'client_id' => $client,
            'message_id' => $message,
            'target_id' => $target,
            'state' => $state,
            'attempt_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return compact('client', 'target', 'message', 'delivery');
    }

    public function test_claim_leases_an_owned_staged_delivery_without_sending(): void
    {
        $fixture = $this->fixture('claimable');
        $leases = app(DeliveryLeaseManager::class);
        $token = $leases->claim($fixture['client'], $fixture['delivery']);

        $this->assertIsString($token);
        $this->assertSame(36, strlen($token));
        $this->assertDatabaseHas('deliveries', [
            'id' => $fixture['delivery'], 'state' => 'leased', 'lease_token' => $token,
        ]);
        $this->assertNull($leases->claim($fixture['client'], $fixture['delivery']));
        $this->assertDatabaseCount('delivery_attempts', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_wrong_tenant_cannot_claim_or_renew(): void
    {
        $one = $this->fixture('lease-one');
        $other = $this->fixture('lease-other');
        $leases = app(DeliveryLeaseManager::class);

        $this->assertNull($leases->claim($other['client'], $one['delivery']));
        $token = $leases->claim($one['client'], $one['delivery']);
        $this->assertIsString($token);
        $this->assertFalse($leases->renew($other['client'], $one['delivery'], $token));
        $this->assertFalse($leases->quarantineExpired($other['client'], $one['delivery']));
    }

    public function test_claim_rejects_queued_disabled_client_or_target(): void
    {
        $queued = $this->fixture('lease-queued', 'queued');
        $suspended = $this->fixture('lease-suspended');
        $targetOff = $this->fixture('lease-target-off');
        DB::table('clients')->where('id', $suspended['client'])->update(['status' => 'suspended']);
        DB::table('delivery_targets')->where('id', $targetOff['target'])->update(['enabled' => false]);
        $leases = app(DeliveryLeaseManager::class);

        foreach ([$queued, $suspended, $targetOff] as $fixture) {
            $this->assertNull($leases->claim($fixture['client'], $fixture['delivery']));
            $this->assertDatabaseHas('deliveries', ['id' => $fixture['delivery'], 'lease_token' => null]);
        }
    }

    public function test_expired_message_is_marked_expired_instead_of_claimed(): void
    {
        $fixture = $this->fixture('lease-expired', 'awaiting_provider', -15);
        $this->assertNull(app(DeliveryLeaseManager::class)->claim($fixture['client'], $fixture['delivery']));
        $this->assertDatabaseHas('deliveries', [
            'id' => $fixture['delivery'], 'state' => 'expired', 'lease_token' => null,
        ]);
    }

    public function test_only_current_unexpired_fencing_token_can_renew(): void
    {
        $fixture = $this->fixture('lease-renew');
        $leases = app(DeliveryLeaseManager::class);
        $token = $leases->claim($fixture['client'], $fixture['delivery'], 30);
        $this->assertIsString($token);

        $this->assertFalse($leases->renew($fixture['client'], $fixture['delivery'], (string) Str::uuid()));
        $this->assertTrue($leases->renew($fixture['client'], $fixture['delivery'], $token, 60));
        $this->assertDatabaseHas('deliveries', ['id' => $fixture['delivery'], 'state' => 'leased']);
    }

    public function test_expired_lease_is_quarantined_and_cannot_be_reused(): void
    {
        $fixture = $this->fixture('lease-quarantine');
        $leases = app(DeliveryLeaseManager::class);
        $token = $leases->claim($fixture['client'], $fixture['delivery']);
        $this->assertIsString($token);

        DB::table('deliveries')->where('id', $fixture['delivery'])
            ->update(['lease_expires_at' => now()->utc()->subSecond()]);

        $this->assertFalse($leases->renew($fixture['client'], $fixture['delivery'], $token));
        $this->assertTrue($leases->quarantineExpired($fixture['client'], $fixture['delivery']));
        $this->assertFalse($leases->quarantineExpired($fixture['client'], $fixture['delivery']));
        $this->assertNull($leases->claim($fixture['client'], $fixture['delivery']));
        $this->assertDatabaseHas('deliveries', [
            'id' => $fixture['delivery'],
            'state' => 'review_required',
            'lease_token' => null,
            'lease_expires_at' => null,
            'last_error_code' => 'LEASE_EXPIRED_UNCERTAIN',
        ]);
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_active_lease_is_never_quarantined(): void
    {
        $fixture = $this->fixture('lease-not-expired');
        $leases = app(DeliveryLeaseManager::class);
        $token = $leases->claim($fixture['client'], $fixture['delivery']);
        $this->assertIsString($token);

        $this->assertFalse($leases->quarantineExpired($fixture['client'], $fixture['delivery']));
        $this->assertDatabaseHas('deliveries', [
            'id' => $fixture['delivery'], 'state' => 'leased', 'lease_token' => $token,
        ]);
    }

    public function test_invalid_lease_ttl_is_rejected_before_database_writes(): void
    {
        $fixture = $this->fixture('lease-invalid-ttl');
        $leases = app(DeliveryLeaseManager::class);

        $this->expectException(InvalidArgumentException::class);
        $leases->claim($fixture['client'], $fixture['delivery'], 0);
    }
}
