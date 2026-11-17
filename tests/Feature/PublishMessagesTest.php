<?php

namespace Tests\Feature;

use App\Jobs\StageDeliveryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublishMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('relay.publish_enabled', true);
    }

    private function createClient(string $slug): int
    {
        return DB::table('clients')->insertGetId([
            'slug' => $slug,
            'name' => $slug,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function issueToken(int $clientId, array $scopes = ['messages.publish']): string
    {
        $token = 'relay_'.bin2hex(random_bytes(32));
        DB::table('api_keys')->insert([
            'client_id' => $clientId,
            'label' => 'Machine publish tests',
            'key_prefix' => substr($token, 0, 16),
            'key_hash' => hash('sha256', $token),
            'scopes' => json_encode($scopes, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        return $token;
    }

    private function target(int $clientId, string $value = 'user@example.test'): int
    {
        return DB::table('delivery_targets')->insertGetId([
            'client_id' => $clientId,
            'channel' => 'email',
            'address_ciphertext' => Crypt::encryptString($value),
            'address_hmac' => hash('sha256', $value),
            'enabled' => true,
            'created_at' => now(),
        ]);
    }

    private function sampleRequest(array $ids): array
    {
        return [
            'target_ids' => $ids,
            'payload' => ['event' => 'verify', 'secret' => 'sensitive-value-123'],
            'priority' => 'normal',
            'ttl_seconds' => 1800,
        ];
    }

    public function test_publishing_is_disabled_by_default(): void
    {
        config()->set('relay.publish_enabled', false);
        $client = $this->createClient('disabled-publisher');
        $target = $this->target($client);

        $this->withToken($this->issueToken($client))
            ->withHeader('Idempotency-Key', 'disabled-publish-test-1')
            ->postJson('/api/v1/messages', $this->sampleRequest([$target]))
            ->assertStatus(503);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_publish_persists_encrypted_message_deliveries_and_only_id_jobs(): void
    {
        $client = $this->createClient('publisher');
        $token = $this->issueToken($client);
        $targetOne = $this->target($client, 'a@example.test');
        $targetTwo = $this->target($client, 'b@example.test');

        $response = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'first-message-request-0001')
            ->postJson('/api/v1/messages', $this->sampleRequest([$targetOne, $targetTwo]));

        $response->assertStatus(202)->assertJsonPath('data.state', 'stored')
            ->assertJsonPath('data.replayed', false);
        $this->assertCount(2, $response->json('data.delivery_ids'));

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('deliveries', 2);
        $this->assertDatabaseCount('jobs', 2);
        $this->assertDatabaseCount('delivery_attempts', 0);

        $message = DB::table('messages')->first();
        $this->assertSame($client, (int) $message->client_id);
        $this->assertSame('direct', $message->kind);
        $this->assertSame('normal', $message->priority);
        $this->assertStringNotContainsString('sensitive-value-123', $message->payload_ciphertext);
        $this->assertNotSame('', $message->idempotency_key_hash);
        $this->assertSame(64, strlen($message->payload_hash));

        $queuePayload = implode("\n", DB::table('jobs')->pluck('payload')->all());
        $this->assertStringNotContainsString('sensitive-value-123', $queuePayload);
        $this->assertStringNotContainsString('a@example.test', $queuePayload);
        $this->assertStringContainsString(StageDeliveryJob::class, $queuePayload);
    }

    public function test_same_idempotency_key_replays_without_new_message_or_jobs(): void
    {
        $client = $this->createClient('replay-client');
        $token = $this->issueToken($client);
        $firstTarget = $this->target($client, 'one@example.test');
        $secondTarget = $this->target($client, 'two@example.test');
        $header = 'replay-message-request-0002';

        $first = $this->withToken($token)->withHeader('Idempotency-Key', $header)
            ->postJson('/api/v1/messages', $this->sampleRequest([$secondTarget, $firstTarget]));
        $second = $this->withToken($token)->withHeader('Idempotency-Key', $header)
            ->postJson('/api/v1/messages', $this->sampleRequest([$firstTarget, $secondTarget]));

        $first->assertStatus(202);
        $second->assertOk()->assertJsonPath('data.replayed', true)
            ->assertJsonPath('data.message_id', $first->json('data.message_id'));
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('deliveries', 2);
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_reusing_idempotency_key_with_different_body_returns_conflict(): void
    {
        $client = $this->createClient('conflict-client');
        $token = $this->issueToken($client);
        $target = $this->target($client);
        $header = 'conflicting-request-0003';

        $this->withToken($token)->withHeader('Idempotency-Key', $header)
            ->postJson('/api/v1/messages', $this->sampleRequest([$target]))->assertStatus(202);

        $changed = $this->sampleRequest([$target]);
        $changed['payload']['secret'] = 'changed';
        $this->withToken($token)->withHeader('Idempotency-Key', $header)
            ->postJson('/api/v1/messages', $changed)->assertStatus(409);

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_targets_from_another_tenant_are_rejected_without_writes(): void
    {
        $client = $this->createClient('tenant-a');
        $other = $this->createClient('tenant-b');
        $target = $this->target($other);

        $this->withToken($this->issueToken($client))
            ->withHeader('Idempotency-Key', 'cross-tenant-request-0004')
            ->postJson('/api/v1/messages', $this->sampleRequest([$target]))
            ->assertStatus(422);

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_key_without_publish_scope_is_forbidden(): void
    {
        $client = $this->createClient('unscoped');
        $target = $this->target($client);

        $this->withToken($this->issueToken($client, ['messages.read']))
            ->withHeader('Idempotency-Key', 'unscoped-request-0005')
            ->postJson('/api/v1/messages', $this->sampleRequest([$target]))
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_bad_idempotency_key_and_duplicate_target_ids_are_rejected(): void
    {
        $client = $this->createClient('bad-request');
        $target = $this->target($client);
        $token = $this->issueToken($client);

        $this->withToken($token)->withHeader('Idempotency-Key', 'short')
            ->postJson('/api/v1/messages', $this->sampleRequest([$target]))->assertStatus(422);
        $this->withToken($token)->withHeader('Idempotency-Key', 'duplicate-target-0006')
            ->postJson('/api/v1/messages', $this->sampleRequest([$target, $target]))
            ->assertUnprocessable();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_staging_job_never_sends_or_creates_a_provider_attempt(): void
    {
        Queue::fake();
        $client = $this->createClient('staging');
        $target = $this->target($client);

        $response = $this->withToken($this->issueToken($client))
            ->withHeader('Idempotency-Key', 'staging-request-0007')
            ->postJson('/api/v1/messages', $this->sampleRequest([$target]));

        $response->assertStatus(202);
        Queue::assertPushed(StageDeliveryJob::class, 1);
        $deliveryId = $response->json('data.delivery_ids.0');

        (new StageDeliveryJob($deliveryId))->handle();
        (new StageDeliveryJob($deliveryId))->handle();

        $this->assertDatabaseHas('deliveries', ['id' => $deliveryId, 'state' => 'awaiting_provider']);
        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_idempotency_keys_are_separate_for_different_clients(): void
    {
        $firstClient = $this->createClient('one');
        $secondClient = $this->createClient('two');
        $firstTarget = $this->target($firstClient);
        $secondTarget = $this->target($secondClient);
        $key = 'shared-idempotency-key-0008';

        $this->withToken($this->issueToken($firstClient))->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/messages', $this->sampleRequest([$firstTarget]))->assertStatus(202);
        $this->withToken($this->issueToken($secondClient))->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/messages', $this->sampleRequest([$secondTarget]))->assertStatus(202);

        $this->assertDatabaseCount('messages', 2);
    }
}
