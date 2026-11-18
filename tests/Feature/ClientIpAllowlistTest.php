<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientIpAllowlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('relay.audit_hmac_key', bin2hex(random_bytes(32)));
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'two_factor_secret' => 'test-placeholder',
            'two_factor_confirmed_at' => now(),
        ]);
        $role = DB::table('roles')->insertGetId([
            'scope' => 'platform', 'code' => 'platform_admin', 'created_at' => now(),
        ]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id, 'role_id' => $role, 'created_at' => now(),
        ]);

        return $user;
    }

    private function client(string $slug): int
    {
        return DB::table('clients')->insertGetId([
            'slug' => $slug, 'name' => $slug, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_cidr_is_normalized_and_duplicate_network_is_rejected(): void
    {
        $client = $this->client('first');
        $this->actingAs($this->admin())->withSession(['auth.password_confirmed_at' => time()]);
        $this->post("/clients/{$client}/ips", ['cidr' => '192.0.2.42/24'])->assertRedirect();
        $this->assertDatabaseHas('client_ip_allowlist', [
            'client_id' => $client, 'cidr' => '192.0.2.0/24',
        ]);
        $this->post("/clients/{$client}/ips", ['cidr' => '192.0.2.5/24'])
            ->assertSessionHasErrors('cidr');
        $this->assertSame(1, DB::table('client_ip_allowlist')->where('client_id', $client)->count());
    }

    public function test_network_with_zero_mask_is_rejected(): void
    {
        $client = $this->client('first');
        $this->actingAs($this->admin())->withSession(['auth.password_confirmed_at' => time()]);
        $this->post("/clients/{$client}/ips", ['cidr' => '0.0.0.0/0'])
            ->assertSessionHasErrors('cidr');
    }

    public function test_last_rule_cannot_be_removed_without_explicit_unrestricted_confirmation(): void
    {
        $client = $this->client('first');
        $rule = DB::table('client_ip_allowlist')->insertGetId([
            'client_id' => $client, 'cidr' => '203.0.113.0/24',
            'enabled' => true, 'created_at' => now(),
        ]);
        $this->actingAs($this->admin())->withSession(['auth.password_confirmed_at' => time()]);
        $this->delete("/clients/{$client}/ips/{$rule}")
            ->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('client_ip_allowlist', ['id' => $rule]);

        $this->delete("/clients/{$client}/ips/{$rule}", ['confirmation' => 'UNRESTRICT'])
            ->assertRedirect(route('clients.ips.index', $client));
        $this->assertDatabaseMissing('client_ip_allowlist', ['id' => $rule]);
        $this->assertDatabaseHas('security_events', ['event_code' => 'client.ip_allowlist.deleted']);
    }

    public function test_cross_client_rule_deletion_is_forbidden(): void
    {
        $first = $this->client('first');
        $second = $this->client('second');
        $rule = DB::table('client_ip_allowlist')->insertGetId([
            'client_id' => $first, 'cidr' => '203.0.113.0/24',
            'enabled' => true, 'created_at' => now(),
        ]);
        $this->actingAs($this->admin())->withSession(['auth.password_confirmed_at' => time()]);
        $this->delete("/clients/{$second}/ips/{$rule}", ['confirmation' => 'UNRESTRICT'])
            ->assertNotFound();
        $this->assertDatabaseHas('client_ip_allowlist', ['id' => $rule, 'client_id' => $first]);
    }
}
