<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PlatformSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('relay.audit_hmac_key', bin2hex(random_bytes(32)));
    }

    private function platformUser(string $role, bool $mfa = false): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => $mfa ? now() : null,
            'two_factor_secret' => $mfa ? 'encrypted-by-fortify-in-production' : null,
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'scope' => 'platform', 'code' => $role, 'created_at' => now(),
        ]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(),
        ]);

        return $user;
    }

    public function test_guests_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_platform_staff_without_mfa_are_sent_to_setup(): void
    {
        $this->actingAs($this->platformUser('super_admin'))->get('/dashboard')
            ->assertRedirect(route('security.setup'));
    }

    public function test_support_cannot_change_feature_flags(): void
    {
        $user = $this->platformUser('support', true);
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/system/feature-flags/webhook-delivery', ['enabled' => '1'])
            ->assertForbidden();
    }

    public function test_recovery_codes_require_recent_password_confirmation(): void
    {
        $user = $this->platformUser('super_admin', true);
        $this->actingAs($user)->get('/security/recovery-codes')
            ->assertRedirect('/user/confirm-password');
    }

    public function test_super_admin_can_change_optional_flag_with_audit(): void
    {
        $user = $this->platformUser('super_admin', true);
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/system/feature-flags/webhook-delivery', ['enabled' => '1'])
            ->assertRedirect();
        $this->assertTrue(Feature::globally()->active('webhook-delivery'));
        $this->assertDatabaseHas('security_events', ['event_code' => 'feature_flag.changed']);
    }
}
