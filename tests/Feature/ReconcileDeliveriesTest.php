<?php

namespace Tests\Feature;

use App\Services\DeliveryLeaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReconcileDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        Http::preventStrayRequests();
        Http::fake();
    }

    /** @return array{client:int,delivery:string} */
    private function delivery(string $slug, string $state = 'queued', int $ttl = 3600): array
    {
        $now = now()->utc();
        $client = DB::table('clients')->insertGetId([
            'slug' => $slug, 'name' => $slug, 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $target = DB::table('delivery_targets')->insertGetId([
            'client_id' => $client, 'channel' => 'sms',
            'address_ciphertext' => Crypt::encryptString('09123456789'),
            'address_hmac' => hash('sha256', $slug),
            'enabled' => true, 'created_at' => $now,
        ]);
        $message = (string) Str::ulid();
        DB::table('messages')->insert([
            'id' => $message, 'client_id' => $client, 'kind' => 'direct',
            'priority' => 'normal',
            'payload_ciphertext' => Crypt::encryptString('{"test":true}'),
            'payload_hash' => hash('sha256', $slug),
            'expires_at' => $now->copy()->addSeconds($ttl),
            'created_at' => $now,
        ]);
        $delivery = (string) Str::ulid();
        DB::table('deliveries')->insert([
            'id' => $delivery, 'client_id' => $client, 'message_id' => $message,
            'target_id' => $target, 'state' => $state, 'attempt_count' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return compact('client', 'delivery');
    }

    public function test_dry_run_does_not_modify_queued_delivery(): void
    {
        $row = $this->delivery('reconcile-preview');
        $this->artisan('relay:reconcile-deliveries')->assertSuccessful();
        $this->assertDatabaseHas('deliveries', ['id' => $row['delivery'], 'state' => 'queued']);
        Http::assertNothingSent();
    }

    public function test_apply_stages_never_attempted_delivery_only_once(): void
    {
        $row = $this->delivery('reconcile-staged');
        $this->artisan('relay:reconcile-deliveries', ['--apply' => true])->assertSuccessful();
        $this->artisan('relay:reconcile-deliveries', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseHas('deliveries', [
            'id' => $row['delivery'], 'state' => 'awaiting_provider', 'attempt_count' => 0,
        ]);
        $this->assertDatabaseCount('delivery_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_expired_queued_delivery_cannot_be_reactivated(): void
    {
        $row = $this->delivery('reconcile-expired', 'queued', -30);
        $this->artisan('relay:reconcile-deliveries', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseHas('deliveries', ['id' => $row['delivery'], 'state' => 'expired']);
        Http::assertNothingSent();
    }

    public function test_expired_lease_is_quarantined_without_retry(): void
    {
        $row = $this->delivery('reconcile-leased', 'awaiting_provider');
        $token = app(DeliveryLeaseManager::class)->claim($row['client'], $row['delivery']);
        $this->assertNotNull($token);
        DB::table('deliveries')->where('id', $row['delivery'])
            ->update(['lease_expires_at' => now()->utc()->subMinute()]);

        $this->artisan('relay:reconcile-deliveries', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseHas('deliveries', [
            'id' => $row['delivery'], 'state' => 'review_required',
            'last_error_code' => 'LEASE_EXPIRED_UNCERTAIN',
            'lease_token' => null,
        ]);
        $this->assertDatabaseCount('delivery_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_queued_delivery_with_attempt_evidence_is_never_staged(): void
    {
        $row = $this->delivery('reconcile-attempt');
        DB::table('deliveries')->where('id', $row['delivery'])
            ->update(['attempt_count' => 1]);
        $this->artisan('relay:reconcile-deliveries', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseHas('deliveries', [
            'id' => $row['delivery'], 'state' => 'queued', 'attempt_count' => 1,
        ]);
        Http::assertNothingSent();
    }

    public function test_wrong_limit_fails_closed(): void
    {
        $this->artisan('relay:reconcile-deliveries', ['--limit' => 501, '--apply' => true])
            ->assertFailed();
        Http::assertNothingSent();
    }
}
