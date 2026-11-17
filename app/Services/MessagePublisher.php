<?php

namespace App\Services;

use App\Jobs\StageDeliveryJob;
use App\Support\InvalidPublishTargets;
use App\Support\MessagePublicationConflict;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LogicException;

/**
 * Persists a direct message and its per-target deliveries atomically with
 * native Laravel database queue records. No provider call happens here.
 */
final class MessagePublisher
{
    /**
     * @param  list<int>  $targetIds
     * @param  array<string, mixed>  $payload
     *
     * @return array{message_id: string, delivery_ids: list<string>, replayed: bool}
     */
    public function publish(
        int $clientId,
        int $keyId,
        string $idempotencyKey,
        array $targetIds,
        array $payload,
        string $priority = 'normal',
        int $ttlSeconds = 3600,
    ): array {
        $queueConnection = config('queue.connections.database.connection');
        if ($queueConnection !== null && $queueConnection !== DB::getDefaultConnection()) {
            throw new LogicException('Atomic publication requires the queue to share the application database connection.');
        }

        sort($targetIds, SORT_NUMERIC);
        $payload = self::canonicalize($payload);
        $canonical = json_encode([
            'target_ids' => $targetIds,
            'payload' => $payload,
            'priority' => $priority,
            'ttl_seconds' => $ttlSeconds,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $fingerprint = hash_hmac('sha256', $canonical, 'relay-publish-fingerprint|'.config('app.key'));
        $idempotencyHash = hash_hmac('sha256', $idempotencyKey, 'relay-publish-idempotency|'.config('app.key'));

        try {
            return DB::transaction(function () use (
                $clientId, $keyId, $idempotencyHash, $fingerprint, $targetIds, $payload, $priority, $ttlSeconds
            ): array {
                $existing = DB::table('messages')
                    ->where('client_id', $clientId)
                    ->where('idempotency_key_hash', $idempotencyHash)
                    ->lockForUpdate()->first();

                if ($existing) {
                    return $this->replay($clientId, $existing, $fingerprint);
                }

                $client = DB::table('clients')->where('id', $clientId)->lockForUpdate()->first();
                $key = DB::table('api_keys')->where('id', $keyId)->where('client_id', $clientId)
                    ->whereNull('revoked_at')->lockForUpdate()->first();
                if (! $client || $client->status !== 'active' || ! $key
                    || ($key->expires_at !== null && $key->expires_at <= now()->toDateTimeString())) {
                    throw new InvalidPublishTargets('Client or credential is no longer active.');
                }

                $validTargets = DB::table('delivery_targets')
                    ->where('client_id', $clientId)
                    ->where('enabled', true)
                    ->whereIn('id', $targetIds)
                    ->lockForUpdate()
                    ->pluck('id')->map(fn ($id): int => (int) $id)->all();

                if (count($validTargets) !== count($targetIds)) {
                    throw new InvalidPublishTargets('The target selection is not available.');
                }

                $messageId = (string) Str::ulid();
                $created = now();
                DB::table('messages')->insert([
                    'id' => $messageId,
                    'client_id' => $clientId,
                    'topic_id' => null,
                    'template_id' => null,
                    'api_key_id' => $keyId,
                    'kind' => 'direct',
                    'priority' => $priority,
                    'payload_ciphertext' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
                    'payload_hash' => $fingerprint,
                    'idempotency_key_hash' => $idempotencyHash,
                    'scheduled_at' => null,
                    'expires_at' => $created->copy()->addSeconds($ttlSeconds),
                    'created_at' => $created,
                ]);

                $deliveryIds = [];
                foreach ($targetIds as $targetId) {
                    $deliveryId = (string) Str::ulid();
                    DB::table('deliveries')->insert([
                        'id' => $deliveryId,
                        'client_id' => $clientId,
                        'message_id' => $messageId,
                        'target_id' => $targetId,
                        'subscription_id' => null,
                        'state' => 'queued',
                        'attempt_count' => 0,
                        'created_at' => $created,
                        'updated_at' => $created,
                    ]);

                    // Queue uses the SAME database transaction. A rollback
                    // removes both the domain rows and the jobs.
                    Queue::connection('database')->pushOn('default', new StageDeliveryJob($deliveryId));
                    $deliveryIds[] = $deliveryId;
                }

                return ['message_id' => $messageId, 'delivery_ids' => $deliveryIds, 'replayed' => false];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Two requests may race on the tenant+idempotency unique index.
            $existing = DB::table('messages')
                ->where('client_id', $clientId)->where('idempotency_key_hash', $idempotencyHash)->first();
            if ($existing) {
                return $this->replay($clientId, $existing, $fingerprint);
            }

            throw $exception;
        }
    }

    /** @return array{message_id: string, delivery_ids: list<string>, replayed: bool} */
    private function replay(int $clientId, object $message, string $fingerprint): array
    {
        if (! hash_equals((string) $message->payload_hash, $fingerprint)) {
            throw new MessagePublicationConflict('This idempotency key was already used for a different request.');
        }

        return [
            'message_id' => (string) $message->id,
            'delivery_ids' => DB::table('deliveries')->where('client_id', $clientId)
                ->where('message_id', $message->id)->orderBy('id')->pluck('id')->all(),
            'replayed' => true,
        ];
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $child) {
            $value[$key] = self::canonicalize($child);
        }

        return $value;
    }
}
