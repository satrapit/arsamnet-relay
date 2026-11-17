<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MachineApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('relay.audit_hmac_key', bin2hex(random_bytes(32)));
    }

    private function client(string $slug): int
    {
        return DB::table('clients')->insertGetId([
            'slug' => $slug,
            'name' => $slug,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function issue(int $client): string
    {
        $token = 'relay_'.bin2hex(random_bytes(32));
        DB::table('api_keys')->insert([
            'client_id' => $client, 'label' => 'Testing',
            'key_prefix' => substr($token, 0, 16),
            'key_hash' => hash('sha256', $token),
            'scopes' => json_encode(['messages.publish']),
            'created_at' => now(),
        ]);

        return $token;
    }

    public function test_status_is_public_but_machine_context_requires_key(): void
    {
        $this->getJson('/api/v1/status')->assertOk()->assertJsonPath('status', 'ok');
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_machine_key_is_tenant_bound_and_has_no_raw_database_copy(): void
    {
        $clientA = $this->client('client-a');
        $this->client('client-b');
        $token = $this->issue($clientA);

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.client_slug', 'client-a')
            ->assertJsonPath('data.client_id', $clientA);
        $this->assertDatabaseMissing('api_keys', ['key_hash' => $token]);
        $this->assertDatabaseHas('api_keys', ['key_hash' => hash('sha256', $token)]);
    }

    public function test_suspended_client_key_is_denied(): void
    {
        $client = $this->client('suspended');
        $token = $this->issue($client);
        DB::table('clients')->where('id', $client)->update(['status' => 'suspended']);

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_ip_allowlist_rejects_non_matching_address(): void
    {
        $client = $this->client('restricted');
        $token = $this->issue($client);
        DB::table('client_ip_allowlist')->insert([
            'client_id' => $client, 'cidr' => '192.0.2.0/24',
            'enabled' => true, 'created_at' => now(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_noncanonical_ip_rule_still_matches_using_network_prefix(): void
    {
        $client = $this->client('canonicalized');
        $token = $this->issue($client);
        DB::table('client_ip_allowlist')->insert([
            'client_id' => $client, 'cidr' => '192.0.2.17/24',
            'enabled' => true, 'created_at' => now(),
        ]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.25'])
            ->withToken($token)->getJson('/api/v1/me')->assertOk();
    }

    public function test_revoked_token_is_denied(): void
    {
        $client = $this->client('revoked');
        $token = $this->issue($client);
        DB::table('api_keys')->where('client_id', $client)->update(['revoked_at' => now()]);

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
