<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
        Http::preventStrayRequests();
        Http::fake();
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

}
