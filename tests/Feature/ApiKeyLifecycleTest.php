<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiKeyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('relay.audit_hmac_key', bin2hex(random_bytes(32)));
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => 'test-only-secret-placeholder',
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'code' => 'super_admin', 'scope' => 'platform', 'created_at' => now(),
        ]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(),
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

    private function issueExistingKey(int $client, User $user, string $token): int
    {
        return DB::table('api_keys')->insertGetId([
            'client_id' => $client,
            'created_by_user_id' => $user->id,
            'label' => 'First key',
            'key_prefix' => substr($token, 0, 16),
            'key_hash' => hash('sha256', $token),
            'scopes' => json_encode(['messages.read']),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
        ]);
    }

    public function test_issued_keys_have_expiration_and_are_only_stored_hashed(): void
    {
        $admin = $this->admin();
        $client = $this->client('alpha');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post("/clients/{$client}/keys", [
                'label' => 'Production test',
                'scopes' => ['messages.read'],
                'ttl_days' => 7,
            ])->assertOk()->assertSee('API key created');

        $issued = DB::table('api_keys')->where('client_id', $client)->first();
        $this->assertNotNull($issued->expires_at);
        $this->assertSame(64, strlen($issued->key_hash));
        $this->assertSame(['messages.read'], json_decode($issued->scopes, true));
        $this->assertDatabaseHas('security_events', ['event_code' => 'api.key.issued']);
    }

    public function test_rotating_client_key_revokes_old_one_and_preserves_scopes(): void
    {
        $admin = $this->admin();
        $client = $this->client('alpha');
        $token = 'relay_'.str_repeat('b', 64);
        $id = $this->issueExistingKey($client, $admin, $token);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post("/clients/{$client}/keys/{$id}/rotate")
            ->assertOk()->assertSee('API key created');

        $old = DB::table('api_keys')->where('id', $id)->first();
        $new = DB::table('api_keys')->where('client_id', $client)->where('id', '!=', $id)->first();
        $this->assertNotNull($old->revoked_at);
        $this->assertNotNull($new);
        $this->assertSame($old->scopes, $new->scopes);
        $this->assertNotSame($old->key_hash, $new->key_hash);
        $this->assertNotNull($new->expires_at);
        $this->assertDatabaseHas('security_events', ['event_code' => 'api.key.rotated']);
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_rotation_cannot_move_a_key_between_clients(): void
    {
        $admin = $this->admin();
        $first = $this->client('first');
        $second = $this->client('second');
        $id = $this->issueExistingKey($first, $admin, 'relay_'.str_repeat('c', 64));

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post("/clients/{$second}/keys/{$id}/rotate")
            ->assertNotFound();
        $this->assertNull(DB::table('api_keys')->where('id', $id)->value('revoked_at'));
        $this->assertSame(0, DB::table('api_keys')->where('client_id', $second)->count());
    }
}
