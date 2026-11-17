<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class KavenegarClientSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('relay.audit_hmac_key', bin2hex(random_bytes(32)));
    }

    private function client(string $slug): int
    {
        return DB::table('clients')->insertGetId([
            'slug' => $slug, 'name' => $slug, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(int $clientId, string $role, bool $mfa = true): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => $mfa ? now() : null,
            'two_factor_secret' => $mfa ? 'fortify-test-only' : null,
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'scope' => 'client', 'code' => $role, 'created_at' => now(),
        ]);
        DB::table('client_memberships')->insert([
            'client_id' => $clientId, 'user_id' => $user->id,
            'role_id' => $roleId, 'status' => 'active', 'created_at' => now(),
        ]);

        return $user;
    }

    private function configData(): array
    {
        return [
            'display_name' => 'Tenant-managed Kavenegar',
            'api_key' => 'TEST_KAVENEGAR_TENANT_KEY_9876543210',
            'sender' => '10001234',
            'priority' => 10,
            'enabled' => '1',
        ];
    }

    public function test_feature_flag_defaults_off_for_client_owner(): void
    {
        $client = $this->client('selfservice-off');
        $owner = $this->member($client, 'owner');

        $this->actingAs($owner)
            ->get(route('account.clients.providers.kavenegar.index', $client))
            ->assertForbidden();

        $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('account.clients.providers.kavenegar.save', $client), $this->configData())
            ->assertForbidden();

        $this->assertDatabaseCount('provider_accounts', 0);
    }

    public function test_client_owner_can_configure_only_own_provider_with_mfa_and_flag(): void
    {
        $client = $this->client('selfservice-owner');
        $owner = $this->member($client, 'owner');
        Feature::activate('client-provider-overrides');

        $this->actingAs($owner)->get(route('account.clients.providers.kavenegar.index', $client))
            ->assertOk()->assertSee('Kavenegar SMS');

        $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('account.clients.providers.kavenegar.save', $client), $this->configData())
            ->assertRedirect(route('account.clients.providers.kavenegar.index', $client));

        $record = DB::table('provider_accounts')->first();
        $this->assertSame($client, (int) $record->client_id);
        $this->assertSame('kavenegar', $record->adapter_code);
        $this->assertSame(1, (int) $record->enabled);
        $this->assertSame(
            $this->configData()['api_key'],
            json_decode(Crypt::decryptString($record->credentials_ciphertext), true)['api_key']
        );

        $page = $this->actingAs($owner)->get(
            route('account.clients.providers.kavenegar.index', $client)
        )->assertOk();

        $this->assertStringNotContainsString($this->configData()['api_key'], $page->getContent());
        $this->assertDatabaseCount('client_provider_routes', 1);
    }

    public function test_unrelated_client_owner_and_read_only_member_cannot_access(): void
    {
        $client = $this->client('selfservice-first');
        $other = $this->client('selfservice-second');
        $owner = $this->member($client, 'owner');
        $viewer = $this->member($other, 'viewer');
        Feature::activate('client-provider-overrides');

        $this->actingAs($owner)
            ->get(route('account.clients.providers.kavenegar.index', $other))->assertForbidden();
        $this->actingAs($viewer)
            ->get(route('account.clients.providers.kavenegar.index', $other))->assertForbidden();

        $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('account.clients.providers.kavenegar.save', $other), $this->configData())
            ->assertForbidden();

        $this->assertDatabaseCount('provider_accounts', 0);
    }

    public function test_client_owner_requires_mfa_and_recent_password_for_secret_changes(): void
    {
        $client = $this->client('selfservice-mfa');
        $withoutMfa = $this->member($client, 'owner', false);
        Feature::activate('client-provider-overrides');

        $this->actingAs($withoutMfa)
            ->get(route('account.clients.providers.kavenegar.index', $client))->assertForbidden();

        $withMfa = $this->member($client, 'client_admin');
        $this->actingAs($withMfa)
            ->post(route('account.clients.providers.kavenegar.save', $client), $this->configData())
            ->assertRedirect('/user/confirm-password');

        $this->assertDatabaseCount('provider_accounts', 0);
    }
}
