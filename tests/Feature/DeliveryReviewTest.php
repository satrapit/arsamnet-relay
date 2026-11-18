<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class DeliveryReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('relay.audit_hmac_key', bin2hex(random_bytes(32)));
        // Fail closed on unexpected HTTP; individual provider tests install
        // their own exact fake so a prior wildcard cannot shadow it.
        Http::preventStrayRequests();
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create([
            'status' => 'active', 'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(), 'two_factor_secret' => 'test-secret',
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'scope' => 'platform', 'code' => $role, 'created_at' => now(),
        ]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(),
        ]);

        return $user;
    }

    /** @return array{client:int,delivery:string} */
    private function fixture(string $slug, string $state = 'review_required'): array
    {
        $client = DB::table('clients')->insertGetId([
            'slug' => $slug, 'name' => $slug, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $target = DB::table('delivery_targets')->insertGetId([
            'client_id' => $client, 'channel' => 'sms',
            'address_ciphertext' => Crypt::encryptString('09123456789'),
            'address_hmac' => hash('sha256', $slug), 'enabled' => true,
            'created_at' => now(),
        ]);
        $messageId = (string) Str::ulid();
        DB::table('messages')->insert([
            'id' => $messageId, 'client_id' => $client, 'kind' => 'direct',
            'priority' => 'normal',
            'payload_ciphertext' => Crypt::encryptString('Sensitive payload'),
            'payload_hash' => hash('sha256', $slug), 'created_at' => now(),
        ]);
        $deliveryId = (string) Str::ulid();
        DB::table('deliveries')->insert([
            'id' => $deliveryId, 'client_id' => $client, 'message_id' => $messageId,
            'target_id' => $target, 'state' => $state, 'attempt_count' => 1,
            'last_error_code' => 'PROVIDER_AMBIGUOUS',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['client' => $client, 'delivery' => $deliveryId];
    }

    public function test_platform_admin_can_list_only_selected_clients_review_queue(): void
    {
        $one = $this->fixture('review-one');
        $other = $this->fixture('review-other');
        $admin = $this->actor('platform_admin');

        $page = $this->actingAs($admin)->get(route('clients.delivery-reviews.index', $one['client']))
            ->assertOk()->assertSee($one['delivery'])->assertDontSee($other['delivery']);
        $this->assertStringNotContainsString('09123456789', $page->getContent());
        $this->assertStringNotContainsString('Sensitive payload', $page->getContent());
        Http::assertNothingSent();
    }

    public function test_acknowledgement_is_signed_and_does_not_resend_or_change_delivery(): void
    {
        $fixture = $this->fixture('review-ack');
        $admin = $this->actor('platform_admin');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('clients.delivery-reviews.acknowledge', [$fixture['client'], $fixture['delivery']]))
            ->assertRedirect(route('clients.delivery-reviews.index', $fixture['client']));

        $this->assertDatabaseHas('deliveries', [
            'id' => $fixture['delivery'], 'state' => 'review_required', 'attempt_count' => 1,
        ]);
        $this->assertDatabaseCount('delivery_attempts', 0);
        $event = DB::table('security_events')->where('event_code', 'delivery.review_acknowledged')->first();
        $this->assertNotNull($event);
        $this->assertSame($fixture['client'], (int) $event->client_id);
        $this->assertSame($admin->id, (int) $event->actor_user_id);
        $this->assertSame($fixture['delivery'], json_decode($event->context_json, true)['delivery_id']);
        $this->assertTrue(SecurityAudit::verifyRecord($event));
        Http::assertNothingSent();
    }

    public function test_support_and_guests_cannot_access_review_actions(): void
    {
        $fixture = $this->fixture('review-unauthorized');
        $url = route('clients.delivery-reviews.acknowledge', [$fixture['client'], $fixture['delivery']]);
        $this->post($url)->assertRedirect();
        $support = $this->actor('support');
        $this->actingAs($support)->get(route('clients.delivery-reviews.index', $fixture['client']))
            ->assertForbidden();
        $this->actingAs($support)->withSession(['auth.password_confirmed_at' => time()])
            ->post($url)->assertForbidden();
        $this->assertDatabaseCount('security_events', 0);
    }

    public function test_foreign_client_and_nonreviewed_delivery_cannot_be_acknowledged(): void
    {
        $one = $this->fixture('review-foreign-one');
        $two = $this->fixture('review-foreign-two', 'accepted');
        $admin = $this->actor('super_admin');
        $session = ['auth.password_confirmed_at' => time()];

        $this->actingAs($admin)->withSession($session)
            ->post(route('clients.delivery-reviews.acknowledge', [$two['client'], $one['delivery']]))
            ->assertNotFound();
        $this->actingAs($admin)->withSession($session)
            ->post(route('clients.delivery-reviews.acknowledge', [$two['client'], $two['delivery']]))
            ->assertNotFound();
        $this->assertDatabaseCount('security_events', 0);
        Http::assertNothingSent();
    }

    public function test_action_requires_recent_password_confirmation(): void
    {
        $fixture = $this->fixture('review-password');
        $admin = $this->actor('platform_admin');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => 0])
            ->post(route('clients.delivery-reviews.acknowledge', [$fixture['client'], $fixture['delivery']]))
            ->assertRedirect('/user/confirm-password');
        $this->assertDatabaseCount('security_events', 0);
        Http::assertNothingSent();
    }

    public function test_invalid_delivery_identifier_cannot_create_audit_event(): void
    {
        $fixture = $this->fixture('review-invalid-id');
        $admin = $this->actor('super_admin');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('clients.delivery-reviews.acknowledge', [$fixture['client'], 'invalid-id']))
            ->assertNotFound();
        $this->assertDatabaseCount('security_events', 0);
        Http::assertNothingSent();
    }

    public function test_missing_audit_key_fails_closed_without_state_change(): void
    {
        $fixture = $this->fixture('review-audit-fail');
        $admin = $this->actor('super_admin');
        config()->set('relay.audit_hmac_key', null);

        try {
            $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
                ->post(route('clients.delivery-reviews.acknowledge', [$fixture['client'], $fixture['delivery']]))
                ->assertStatus(500);
        } finally {
            $this->assertDatabaseHas('deliveries', [
                'id' => $fixture['delivery'], 'state' => 'review_required', 'attempt_count' => 1,
            ]);
            $this->assertDatabaseCount('security_events', 0);
            Http::assertNothingSent();
        }
    }

    public function test_provider_status_gate_blocks_queries_without_network(): void
    {
        $fixture = $this->fixture('status-offline');
        $admin = $this->actor('platform_admin');
        Feature::globally()->deactivate('provider-status-queries');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('clients.delivery-reviews.check-status', [$fixture['client'], $fixture['delivery']]))
            ->assertRedirect();

        $this->assertDatabaseCount('security_events', 0);
        $this->assertDatabaseHas('deliveries', [
            'id' => $fixture['delivery'], 'state' => 'review_required',
        ]);
        Http::assertNothingSent();
    }

    public function test_status_query_denies_foreign_tenant_and_unaccepted_attempt(): void
    {
        $one = $this->fixture('status-foreign');
        $two = $this->fixture('status-other');
        $admin = $this->actor('super_admin');
        Feature::globally()->activate('provider-status-queries');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('clients.delivery-reviews.check-status', [$two['client'], $one['delivery']]))
            ->assertNotFound();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('clients.delivery-reviews.check-status', [$one['client'], $one['delivery']]))
            ->assertNotFound();
        Http::assertNothingSent();
        $this->assertDatabaseCount('security_events', 0);
    }

    public function test_status_query_requires_recent_password_and_platform_role(): void
    {
        $fixture = $this->fixture('status-auth');
        $url = route('clients.delivery-reviews.check-status', [$fixture['client'], $fixture['delivery']]);
        Feature::globally()->activate('provider-status-queries');
        $admin = $this->actor('platform_admin');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => 0])
            ->post($url)->assertRedirect('/user/confirm-password');

        $support = $this->actor('support');
        $this->actingAs($support)->withSession(['auth.password_confirmed_at' => time()])
            ->post($url)->assertForbidden();

        $this->assertDatabaseCount('security_events', 0);
        Http::assertNothingSent();
    }

    public function test_status_query_records_signed_provider_evidence_without_marking_delivered(): void
    {
        $fixture = $this->fixture('status-accepted', 'accepted');
        $admin = $this->actor('platform_admin');
        Feature::globally()->activate('provider-status-queries');
        Feature::globally()->deactivate('kavenegar-sending');
        $accountId = DB::table('provider_accounts')->insertGetId([
            'client_id' => $fixture['client'], 'adapter_code' => 'kavenegar',
            'channel' => 'sms', 'display_name' => 'Test Kavenegar',
            'credentials_ciphertext' => Crypt::encryptString(json_encode([
                'api_key' => 'TEST_KAVENEGAR_KEY_12345678901234567890',
                'sender' => '10001234',
            ], JSON_THROW_ON_ERROR)),
            'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('delivery_attempts')->insert([
            'delivery_id' => $fixture['delivery'],
            'provider_account_id' => $accountId, 'attempt_no' => 1,
            'outcome' => 'accepted', 'provider_ref' => 'kv-987654',
            'created_at' => now(),
        ]);
        Http::fake(['*' => Http::response([
            'return' => ['status' => 200, 'message' => 'Private response'],
            'entries' => [[
                'messageid' => 987654, 'status' => 10,
                'receptor' => '09123456789', 'statustext' => 'Private info',
            ]],
        ], 200)]);

        $page = $this->actingAs($admin)
            ->get(route('clients.delivery-reviews.index', $fixture['client']))
            ->assertOk()->assertSee('Check provider status');
        $this->assertStringNotContainsString('09123456789', $page->getContent());

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('clients.delivery-reviews.check-status', [
                $fixture['client'], $fixture['delivery'],
            ]))->assertRedirect();

        $this->assertDatabaseHas('deliveries', [
            'id' => $fixture['delivery'], 'state' => 'accepted', 'attempt_count' => 1,
        ]);
        $this->assertDatabaseCount('delivery_attempts', 1);
        $audit = DB::table('security_events')
            ->where('event_code', 'delivery.provider_status_queried')->first();
        $this->assertNotNull($audit);
        $this->assertTrue(SecurityAudit::verifyRecord($audit));
        $evidence = json_decode($audit->context_json, true);
        $this->assertSame($fixture['delivery'], $evidence['delivery_id']);
        $this->assertSame(10, $evidence['provider_status_code']);
        $this->assertSame('provider_reports_delivered', $evidence['review_action']);
        $this->assertStringNotContainsString('09123456789', $audit->context_json);
        $this->assertStringNotContainsString('Private response', $audit->context_json);
        Http::assertSentCount(1);
    }

    public function test_delivery_history_only_shows_verified_events_for_exact_tenant_and_delivery(): void
    {
        $one = $this->fixture('history-owned');
        $other = $this->fixture('history-foreign');
        $admin = $this->actor('platform_admin');

        SecurityAudit::record('delivery.review_acknowledged', 'high', $admin->id, [
            'delivery_id' => $one['delivery'], 'review_action' => 'investigating',
        ], $one['client']);
        SecurityAudit::record('delivery.provider_status_queried', 'high', $admin->id, [
            'delivery_id' => $one['delivery'], 'review_action' => 'provider_reports_delivered',
            'provider_status_code' => 10,
        ], $one['client']);
        SecurityAudit::record('delivery.review_acknowledged', 'high', $admin->id, [
            'delivery_id' => $other['delivery'], 'review_action' => 'investigating',
        ], $other['client']);

        $page = $this->actingAs($admin)
            ->get(route('clients.delivery-reviews.history', [$one['client'], $one['delivery']]))
            ->assertOk()
            ->assertSee('provider_reports_delivered')
            ->assertSee('Verified')
            ->assertDontSee($other['delivery']);

        $this->assertStringNotContainsString('09123456789', $page->getContent());
        $this->assertStringNotContainsString('Sensitive payload', $page->getContent());
        $this->assertDatabaseHas('deliveries', [
            'id' => $one['delivery'], 'state' => 'review_required', 'attempt_count' => 1,
        ]);
        Http::assertNothingSent();
    }

    public function test_delivery_history_flags_tampering_and_hides_unverified_metadata(): void
    {
        $fixture = $this->fixture('history-tampering');
        $admin = $this->actor('super_admin');
        SecurityAudit::record('delivery.provider_status_queried', 'high', $admin->id, [
            'delivery_id' => $fixture['delivery'],
            'review_action' => 'provider_reports_delivered',
            'provider_status_code' => 10,
        ], $fixture['client']);
        DB::table('security_events')->update(['severity' => 'critical']);

        $this->actingAs($admin)
            ->get(route('clients.delivery-reviews.history', [$fixture['client'], $fixture['delivery']]))
            ->assertOk()
            ->assertSee('Integrity failed')
            ->assertDontSee('provider_reports_delivered')
            ->assertDontSee('Verified');
        Http::assertNothingSent();
    }

    public function test_delivery_history_requires_platform_permission_and_exact_client(): void
    {
        $one = $this->fixture('history-permission-one');
        $two = $this->fixture('history-permission-two');
        $url = route('clients.delivery-reviews.history', [$one['client'], $one['delivery']]);

        $this->get($url)->assertRedirect();
        $support = $this->actor('support');
        $this->actingAs($support)->get($url)->assertForbidden();

        $admin = $this->actor('super_admin');
        $this->actingAs($admin)
            ->get(route('clients.delivery-reviews.history', [$two['client'], $one['delivery']]))
            ->assertNotFound();
        $this->actingAs($admin)
            ->get(route('clients.delivery-reviews.history', [$one['client'], 'invalid']))
            ->assertNotFound();

        Http::assertNothingSent();
    }
}
