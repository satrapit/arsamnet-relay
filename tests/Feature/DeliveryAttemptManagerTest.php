<?php

namespace Tests\Feature;

use App\Enums\ProviderAttemptOutcome;
use App\Services\DeliveryAttemptManager;
use App\Services\DeliveryLeaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class DeliveryAttemptManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /** @return array{client: int, target: int, delivery: string, provider: int, token: string} */
    private function fixture(string $slug, string $channel = 'email'): array
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
            'channel' => $channel,
            'address_ciphertext' => Crypt::encryptString($slug.'@example.test'),
            'address_hmac' => hash('sha256', $slug.'@example.test'),
            'enabled' => true,
            'created_at' => $now,
        ]);
        $messageId = (string) Str::ulid();
        DB::table('messages')->insert([
            'id' => $messageId,
            'client_id' => $client,
            'kind' => 'direct',
            'priority' => 'normal',
            'payload_ciphertext' => Crypt::encryptString('{"event":"safe"}'),
            'payload_hash' => hash('sha256', $slug),
            'expires_at' => $now->copy()->addHour(),
            'created_at' => $now,
        ]);
        $delivery = (string) Str::ulid();
        DB::table('deliveries')->insert([
            'id' => $delivery,
            'client_id' => $client,
            'message_id' => $messageId,
            'target_id' => $target,
            'state' => 'awaiting_provider',
            'attempt_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $provider = DB::table('provider_accounts')->insertGetId([
            'client_id' => $client,
            'adapter_code' => 'fake-only',
            'channel' => $channel,
            'display_name' => $slug.' fake adapter',
            'credentials_ciphertext' => Crypt::encryptString('fake-credential-not-used'),
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('client_provider_routes')->insert([
            'client_id' => $client,
            'provider_account_id' => $provider,
            'channel' => $channel,
            'priority' => 1,
            'enabled' => true,
            'created_at' => $now,
        ]);
        $token = app(DeliveryLeaseManager::class)->claim($client, $delivery);
        $this->assertIsString($token);

        return compact('client', 'target', 'delivery', 'provider', 'token');
    }

    public function test_begin_persists_one_started_attempt_before_any_provider_work(): void
    {
        $f = $this->fixture('attempt-start');
        $attempts = app(DeliveryAttemptManager::class);

        $id = $attempts->begin($f['client'], $f['delivery'], $f['token'], $f['provider']);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('delivery_attempts', [
            'id' => $id, 'delivery_id' => $f['delivery'],
            'provider_account_id' => $f['provider'],
            'attempt_no' => 1, 'outcome' => 'started',
        ]);
        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'leased', 'attempt_count' => 1,
        ]);
        $this->assertNull($attempts->begin($f['client'], $f['delivery'], $f['token'], $f['provider']));
        $this->assertDatabaseCount('delivery_attempts', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_accepted_provider_response_never_means_delivered(): void
    {
        $f = $this->fixture('attempt-accepted');
        $manager = app(DeliveryAttemptManager::class);
        $id = $manager->begin($f['client'], $f['delivery'], $f['token'], $f['provider']);

        $this->assertTrue($manager->finish(
            $f['client'], $f['delivery'], $f['token'], $id,
            ProviderAttemptOutcome::Accepted, 202, 'opaque-reference-1'
        ));

        $this->assertDatabaseHas('delivery_attempts', [
            'id' => $id, 'outcome' => 'accepted', 'http_status' => 202,
            'provider_ref' => 'opaque-reference-1', 'error_code' => null,
        ]);
        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'accepted', 'lease_token' => null,
            'lease_expires_at' => null, 'last_error_code' => null,
        ]);
        $this->assertFalse($manager->finish(
            $f['client'], $f['delivery'], $f['token'], $id, ProviderAttemptOutcome::Accepted
        ));
        $this->assertDatabaseCount('delivery_attempts', 1);
    }

    public function test_rejected_provider_result_is_terminal_without_retry(): void
    {
        $f = $this->fixture('attempt-rejected');
        $manager = app(DeliveryAttemptManager::class);
        $id = $manager->begin($f['client'], $f['delivery'], $f['token'], $f['provider']);

        $this->assertTrue($manager->finish(
            $f['client'], $f['delivery'], $f['token'], $id,
            ProviderAttemptOutcome::Rejected, 400
        ));
        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'rejected',
            'last_error_code' => 'PROVIDER_REJECTED',
        ]);
        $this->assertDatabaseHas('delivery_attempts', [
            'id' => $id, 'outcome' => 'rejected', 'error_code' => 'PROVIDER_REJECTED',
        ]);
        $this->assertNull($manager->begin($f['client'], $f['delivery'], $f['token'], $f['provider']));
    }

    public function test_retryable_classification_requires_manual_review_and_no_auto_retry(): void
    {
        $f = $this->fixture('attempt-retryable');
        $manager = app(DeliveryAttemptManager::class);
        $id = $manager->begin($f['client'], $f['delivery'], $f['token'], $f['provider']);

        $this->assertTrue($manager->finish(
            $f['client'], $f['delivery'], $f['token'], $id,
            ProviderAttemptOutcome::Retryable, 503
        ));
        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'review_required',
            'last_error_code' => 'PROVIDER_RETRYABLE', 'lease_token' => null,
        ]);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('delivery_attempts', 1);
    }

    public function test_ambiguous_provider_result_is_quarantined_without_retransmission(): void
    {
        $f = $this->fixture('attempt-ambiguous');
        $manager = app(DeliveryAttemptManager::class);
        $id = $manager->begin($f['client'], $f['delivery'], $f['token'], $f['provider']);

        $this->assertTrue($manager->finish(
            $f['client'], $f['delivery'], $f['token'], $id, ProviderAttemptOutcome::Ambiguous
        ));
        $this->assertDatabaseHas('deliveries', [
            'id' => $f['delivery'], 'state' => 'review_required',
            'last_error_code' => 'PROVIDER_AMBIGUOUS',
        ]);
        $this->assertFalse($manager->finish(
            $f['client'], $f['delivery'], $f['token'], $id, ProviderAttemptOutcome::Accepted
        ));
        $this->assertDatabaseCount('delivery_attempts', 1);
    }

    public function test_other_tenant_provider_cannot_be_used_even_with_misconfigured_route(): void
    {
        $own = $this->fixture('attempt-tenant-own');
        $other = $this->fixture('attempt-tenant-other');
        $manager = app(DeliveryAttemptManager::class);

        DB::table('client_provider_routes')->insert([
            'client_id' => $own['client'],
            'provider_account_id' => $other['provider'],
            'channel' => 'email',
            'priority' => 2,
            'enabled' => true,
            'created_at' => now(),
        ]);

        $this->assertNull($manager->begin(
            $own['client'], $own['delivery'], $own['token'], $other['provider']
        ));
        $this->assertNull($manager->begin(
            $other['client'], $own['delivery'], $own['token'], $own['provider']
        ));
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_provider_route_and_enabled_state_are_enforced(): void
    {
        $f = $this->fixture('attempt-route-disabled');
        $manager = app(DeliveryAttemptManager::class);
        DB::table('client_provider_routes')->where('client_id', $f['client'])
            ->update(['enabled' => false]);
        $this->assertNull($manager->begin($f['client'], $f['delivery'], $f['token'], $f['provider']));

        DB::table('client_provider_routes')->where('client_id', $f['client'])
            ->update(['enabled' => true]);
        DB::table('provider_accounts')->where('id', $f['provider'])
            ->update(['enabled' => false]);
        $this->assertNull($manager->begin($f['client'], $f['delivery'], $f['token'], $f['provider']));
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_wrong_or_expired_token_never_starts_or_finishes_attempt(): void
    {
        $f = $this->fixture('attempt-fence');
        $manager = app(DeliveryAttemptManager::class);
        $this->assertNull($manager->begin(
            $f['client'], $f['delivery'], (string) Str::uuid(), $f['provider']
        ));

        $id = $manager->begin($f['client'], $f['delivery'], $f['token'], $f['provider']);
        $this->assertIsInt($id);
        DB::table('deliveries')->where('id', $f['delivery'])
            ->update(['lease_expires_at' => now()->utc()->subSecond()]);

        $this->assertFalse($manager->finish(
            $f['client'], $f['delivery'], $f['token'], $id,
            ProviderAttemptOutcome::Accepted
        ));
        $this->assertDatabaseHas('delivery_attempts', ['id' => $id, 'outcome' => 'started']);
        $this->assertDatabaseHas('deliveries', ['id' => $f['delivery'], 'state' => 'leased']);
    }

    public function test_untrusted_provider_reference_and_status_are_rejected(): void
    {
        $f = $this->fixture('attempt-invalid-meta');
        $manager = app(DeliveryAttemptManager::class);
        $id = $manager->begin($f['client'], $f['delivery'], $f['token'], $f['provider']);

        try {
            $manager->finish(
                $f['client'], $f['delivery'], $f['token'], $id,
                ProviderAttemptOutcome::Accepted, 200, 'person@example.com'
            );
            $this->fail('A potentially identifying provider reference was allowed.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseHas('delivery_attempts', ['id' => $id, 'outcome' => 'started']);
        }

        $this->expectException(InvalidArgumentException::class);
        $manager->finish(
            $f['client'], $f['delivery'], $f['token'], $id,
            ProviderAttemptOutcome::Accepted, 700
        );
    }

    public function test_suspended_client_cannot_begin_an_attempt(): void
    {
        $f = $this->fixture('attempt-client-suspended');
        DB::table('clients')->where('id', $f['client'])->update(['status' => 'suspended']);
        $this->assertNull(app(DeliveryAttemptManager::class)->begin(
            $f['client'], $f['delivery'], $f['token'], $f['provider']
        ));
        $this->assertDatabaseCount('delivery_attempts', 0);
    }
}
