<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KavenegarAccountConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('relay.audit_hmac_key', bin2hex(random_bytes(32)));
    }

    private function client(): int
    {
        return DB::table('clients')->insertGetId([
            'slug' => 'sms-tenant', 'name' => 'SMS Tenant', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function platformUser(string $role): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => 'fortify-fixture',
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'scope' => 'platform', 'code' => $role, 'created_at' => now(),
        ]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(),
        ]);

        return $user;
    }

    private function settings(?string $key = 'TEST_KAVENEGAR_API_1234567890', string $enabled = '1'): array
    {
        return [
            'display_name' => 'Customer Kavenegar',
            'api_key' => $key,
            'sender' => '10001234',
            'priority' => 15,
            'enabled' => $enabled,
        ];
    }

    public function test_admin_can_configure_encrypted_sms_provider_and_redacted_audit(): void
    {
        $client = $this->client();
        $admin = $this->platformUser('platform_admin');
        $key = 'TEST_KAVENEGAR_API_1234567890';

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('clients.providers.kavenegar.save', $client), $this->settings())
            ->assertRedirect(route('clients.providers.kavenegar.index', $client));

        $account = DB::table('provider_accounts')->first();
        $route = DB::table('client_provider_routes')->first();
        $this->assertSame($client, (int) $account->client_id);
        $this->assertSame('kavenegar', $account->adapter_code);
        $this->assertSame('sms', $account->channel);
        $this->assertSame(1, (int) $account->enabled);
        $this->assertSame(15, (int) $route->priority);
        $this->assertSame(1, (int) $route->enabled);
        $this->assertStringNotContainsString($key, $account->credentials_ciphertext);
        $this->assertSame($key, json_decode(Crypt::decryptString($account->credentials_ciphertext), true)['api_key']);

        $audit = DB::table('security_events')
            ->where('event_code', 'provider.kavenegar.configuration_changed')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString($key, $audit->context_json);
        $this->assertStringNotContainsString('10001234', $audit->context_json);

        $page = $this->actingAs($admin)->get(route('clients.providers.kavenegar.index', $client))
            ->assertOk()->assertSee('Kavenegar SMS');
        $this->assertStringNotContainsString($key, $page->getContent());
        $this->assertStringNotContainsString('10001234', $page->getContent());
    }

    public function test_admin_can_rotate_key_and_disable_existing_route(): void
    {
        $client = $this->client();
        $admin = $this->platformUser('super_admin');
        $url = route('clients.providers.kavenegar.save', $client);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post($url, $this->settings())->assertRedirect();

        $nextKey = 'TEST_KAVENEGAR_ROTATED_KEY_987654321';
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post($url, $this->settings($nextKey, '0'))->assertRedirect();

        $this->assertDatabaseCount('provider_accounts', 1);
        $this->assertDatabaseCount('client_provider_routes', 1);
        $account = DB::table('provider_accounts')->first();
        $this->assertSame($nextKey, json_decode(Crypt::decryptString($account->credentials_ciphertext), true)['api_key']);
        $this->assertSame(0, (int) $account->enabled);
        $this->assertSame(0, (int) DB::table('client_provider_routes')->value('enabled'));
    }

    public function test_provider_changes_require_privileged_role_and_recent_password(): void
    {
        $client = $this->client();
        $support = $this->platformUser('support');

        $this->actingAs($support)->get(route('clients.providers.kavenegar.index', $client))
            ->assertForbidden();
        $this->actingAs($support)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('clients.providers.kavenegar.save', $client), $this->settings())
            ->assertForbidden();

        $admin = $this->platformUser('platform_admin');
        $this->actingAs($admin)->get(route('clients.providers.kavenegar.index', $client))
            ->assertOk();
        $this->actingAs($admin)->post(route('clients.providers.kavenegar.save', $client), $this->settings())
            ->assertRedirect('/user/confirm-password');
        $this->assertDatabaseCount('provider_accounts', 0);
    }

    public function test_first_configuration_requires_key(): void
    {
        $client = $this->client();
        $admin = $this->platformUser('platform_admin');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('clients.providers.kavenegar.save', $client), $this->settings(null))
            ->assertSessionHasErrors('api_key');
        $this->assertDatabaseCount('provider_accounts', 0);
    }
}
