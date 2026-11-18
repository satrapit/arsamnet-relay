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
        $this->withoutVite();
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

    public function test_failed_login_does_not_store_password_or_raw_ip(): void
    {
        $this->post('/login', [
            'email' => 'missing@example.invalid',
            'password' => 'example-private-password',
        ])->assertSessionHasErrors('email');

        $event = DB::table('security_events')->where('event_code', 'auth.login.failed')->first();
        $this->assertNotNull($event);
        $this->assertSame('{}', $event->context_json);
        $this->assertSame(64, strlen($event->source_ip_hmac));
        $this->assertStringNotContainsString('example-private-password', $event->context_json);
    }

    public function test_support_cannot_access_platform_security_monitor(): void
    {
        $user = $this->platformUser('support', true);
        $this->actingAs($user)->get('/system/security')->assertForbidden();
    }

    public function test_security_monitor_reports_missing_scheduler_heartbeat(): void
    {
        $user = $this->platformUser('platform_admin', true);
        $this->actingAs($user)->get('/system/security')
            ->assertOk()->assertSee('Scheduler heartbeat is missing or stale');
    }

    public function test_support_cannot_change_feature_flags(): void
    {
        $user = $this->platformUser('support', true);
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('settings.flags.update', 'webhook-delivery'), ['enabled' => '1'])
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
            ->post(route('settings.flags.update', 'webhook-delivery'), ['enabled' => '1'])
            ->assertRedirect();
        $this->assertTrue(Feature::globally()->active('webhook-delivery'));
        $this->assertDatabaseHas('security_events', ['event_code' => 'feature_flag.changed']);
    }

    public function test_super_admin_controls_message_send_and_status_flags_in_dashboard(): void
    {
        $user = $this->platformUser('super_admin', true);
        foreach (['message-publishing', 'kavenegar-sending', 'provider-status-queries'] as $key) {
            $this->assertFalse(Feature::globally()->active($key));
            $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
                ->post(route('settings.flags.update', $key), ['enabled' => '1'])
                ->assertRedirect();
            $this->assertTrue(Feature::globally()->active($key));
        }
        $this->assertDatabaseCount('security_events', 3);
    }

    public function test_only_super_admin_can_change_sms_hourly_limit_with_signed_audit(): void
    {
        $admin = $this->platformUser('platform_admin', true);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('settings.kavenegar-hourly-limit.update'), ['hourly_limit' => 25])
            ->assertForbidden();

        $super = $this->platformUser('super_admin', true);
        $this->actingAs($super)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('settings.kavenegar-hourly-limit.update'), ['hourly_limit' => 25])
            ->assertRedirect();

        $this->assertSame(25, Feature::globally()->value('kavenegar-hourly-limit'));
        $this->assertDatabaseHas('security_events', ['event_code' => 'feature_flag.changed']);

        $this->actingAs($super)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('settings.kavenegar-hourly-limit.update'), ['hourly_limit' => 0])
            ->assertSessionHasErrors('hourly_limit');
        $this->assertSame(25, Feature::globally()->value('kavenegar-hourly-limit'));
    }

    public function test_settings_are_grouped_and_old_url_redirects(): void
    {
        $admin = $this->platformUser('platform_admin', true);
        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Message delivery')
            ->assertSee('Providers &amp; integrations', false)
            ->assertSee('Limits &amp; quotas', false)
            ->assertSee('data-settings-toggle')
            ->assertSee('data-settings-toasts')
            ->assertSee('role="switch"', false)
            ->assertSee('Kavenegar hourly SMS limit');
        $this->get('/system/feature-flags')->assertRedirect('/system/settings');
    }

    public function test_super_admin_can_change_settings_with_json_without_password_confirmation(): void
    {
        $super = $this->platformUser('super_admin', true);
        $this->actingAs($super)->postJson(route('settings.flags.update', 'provider-status-queries'), [
            'enabled' => true,
        ])->assertOk()->assertJsonPath('enabled', true)
            ->assertJsonPath('key', 'provider-status-queries');

        $this->assertTrue(Feature::globally()->active('provider-status-queries'));
        $this->assertDatabaseHas('security_events', ['event_code' => 'feature_flag.changed']);

        $this->postJson(route('settings.kavenegar-hourly-limit.update'), [
            'hourly_limit' => 25,
        ])->assertOk()->assertJsonPath('hourly_limit', 25);
        $this->assertSame(25, Feature::globally()->value('kavenegar-hourly-limit'));
    }

    public function test_settings_json_requires_super_admin_mfa_and_valid_payload(): void
    {
        $support = $this->platformUser('support', true);
        $url = route('settings.flags.update', 'kavenegar-sending');
        $this->actingAs($support)->postJson($url, ['enabled' => true])->assertForbidden();

        $superWithoutMfa = $this->platformUser('super_admin');
        $this->actingAs($superWithoutMfa)->postJson($url, ['enabled' => true])
            ->assertRedirect(route('security.setup'));

        $super = $this->platformUser('super_admin', true);
        $this->actingAs($super)->postJson($url, ['enabled' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('enabled');
        $this->postJson(route('settings.flags.update', 'nonexistent-flag'), ['enabled' => true])
            ->assertNotFound();

        $this->postJson(route('settings.kavenegar-hourly-limit.update'), ['hourly_limit' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('hourly_limit');
        $this->assertFalse(Feature::globally()->active('kavenegar-sending'));
        $this->assertDatabaseCount('security_events', 0);
    }

    public function test_settings_update_still_requires_csrf_for_web_requests(): void
    {
        $super = $this->platformUser('super_admin', true);
        $this->actingAs($super);
        // HTTP feature tests bypass CSRF by default in Laravel's testing
        // environment; assert the route retains web middleware instead.
        $route = app('router')->getRoutes()->getByName('settings.flags.update');
        $this->assertNotNull($route);
        $this->assertContains('web', $route->gatherMiddleware());
    }
}
