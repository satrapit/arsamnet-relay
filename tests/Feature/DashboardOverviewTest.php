<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'scope' => 'platform', 'code' => $role, 'created_at' => now(),
        ]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(),
        ]);

        return $user;
    }

    public function test_dashboard_counts_real_records_and_displays_sanitized_activity(): void
    {
        $admin = $this->staff('platform_admin');
        $client = DB::table('clients')->insertGetId([
            'slug' => 'overview-client', 'name' => 'Overview Client',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $message = (string) Str::ulid();
        DB::table('messages')->insert([
            'id' => $message, 'client_id' => $client, 'kind' => 'direct',
            'priority' => 'normal', 'payload_ciphertext' => 'opaque-encrypted-fixture',
            'payload_hash' => hash('sha256', 'dashboard-fixture'),
            'created_at' => now(),
        ]);
        $target = DB::table('delivery_targets')->insertGetId([
            'client_id' => $client, 'channel' => 'sms',
            'address_ciphertext' => 'opaque-encrypted-recipient',
            'address_hmac' => hash('sha256', 'recipient-fixture'),
            'enabled' => true, 'created_at' => now(),
        ]);
        $delivery = (string) Str::ulid();
        DB::table('deliveries')->insert([
            'id' => $delivery, 'client_id' => $client,
            'message_id' => $message, 'target_id' => $target,
            'state' => 'review_required', 'attempt_count' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $page = $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()->assertSee('Platform overview')
            ->assertSee('Overview Client')
            ->assertSee($delivery)
            ->assertSee('Needs review')
            ->assertSee('SMS sending off');

        $this->assertStringNotContainsString('opaque-encrypted-fixture', $page->getContent());
        $this->assertStringNotContainsString('opaque-encrypted-recipient', $page->getContent());
    }

    public function test_dashboard_displays_enabled_operator_flags_without_changing_them(): void
    {
        $admin = $this->staff('super_admin');
        Feature::globally()->activate('provider-status-queries');
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()->assertSee('Status queries on')->assertSee('SMS sending off');
        $this->assertFalse(Feature::globally()->active('kavenegar-sending'));
    }

    public function test_dashboard_is_not_accessible_to_guest_or_nonplatform_user(): void
    {
        $this->get(route('dashboard'))->assertRedirect('/login');
        $user = User::factory()->create([
            'email_verified_at' => now(), 'two_factor_confirmed_at' => now(),
            'two_factor_secret' => 'test-secret',
        ]);
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_support_can_see_overview_but_not_tenant_delivery_identifiers(): void
    {
        $support = $this->staff('support');
        $client = DB::table('clients')->insertGetId([
            'slug' => 'restricted-tenant', 'name' => 'Restricted Tenant Name',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $message = (string) Str::ulid();
        DB::table('messages')->insert([
            'id' => $message, 'client_id' => $client, 'kind' => 'direct',
            'priority' => 'normal', 'payload_ciphertext' => 'encrypted',
            'payload_hash' => hash('sha256', 'restricted'), 'created_at' => now(),
        ]);
        $target = DB::table('delivery_targets')->insertGetId([
            'client_id' => $client, 'channel' => 'sms',
            'address_ciphertext' => 'encrypted', 'address_hmac' => hash('sha256', 'private'),
            'enabled' => true, 'created_at' => now(),
        ]);
        $delivery = (string) Str::ulid();
        DB::table('deliveries')->insert([
            'id' => $delivery, 'client_id' => $client,
            'message_id' => $message, 'target_id' => $target,
            'state' => 'accepted', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($support)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Restricted Tenant Name')
            ->assertDontSee($delivery);
    }

}
