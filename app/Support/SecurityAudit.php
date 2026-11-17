<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final class SecurityAudit
{
    /**
     * Persist minimal redacted security metadata; privileged operations call this
     * inside their own DB transaction and must fail closed on errors.
     */
    public static function record(
        string $code,
        string $severity,
        ?int $actorUserId,
        array $context = [],
        ?int $clientId = null,
        ?string $sourceIp = null,
    ): void {
        $key = self::signingKey();
        $allowed = [
            'flag_key', 'old_state', 'new_state', 'role_code',
            'target_user_id', 'scope', 'client_id', 'key_id', 'previous_key_id',
        ];
        $safe = array_intersect_key($context, array_flip($allowed));
        foreach ($safe as $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new RuntimeException('Unsupported security audit metadata.');
            }
        }
        ksort($safe, SORT_STRING);
        $created = now()->utc();
        $ipHmac = $sourceIp ? hash_hmac('sha256', $sourceIp, $key) : null;
        $contextJson = json_encode((object) $safe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $record = [
            'client_id' => $clientId,
            'actor_user_id' => $actorUserId,
            'event_code' => $code,
            'severity' => $severity,
            'source_ip_hmac' => $ipHmac,
            'context_json' => $contextJson,
            'created_at' => $created,
        ];
        $record['record_hmac'] = self::sign(
            $key, $code, $severity, $actorUserId, $clientId, $ipHmac,
            $contextJson, $created->format('Y-m-d\TH:i:s\Z'),
        );

        DB::table('security_events')->insert($record);
    }

    /**
     * Detects edits to existing event rows; it cannot detect deleted rows.
     */
    public static function verifyRecord(object $row): bool
    {
        try {
            $decoded = json_decode((string) $row->context_json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return false;
            }
            ksort($decoded, SORT_STRING);
            $contextJson = json_encode((object) $decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $timestamp = Carbon::parse((string) $row->created_at, 'UTC')->format('Y-m-d\TH:i:s\Z');
            $expected = self::sign(
                self::signingKey(), (string) $row->event_code, (string) $row->severity,
                $row->actor_user_id === null ? null : (int) $row->actor_user_id,
                $row->client_id === null ? null : (int) $row->client_id,
                $row->source_ip_hmac === null ? null : (string) $row->source_ip_hmac,
                $contextJson, $timestamp,
            );
            return is_string($row->record_hmac) && hash_equals($expected, $row->record_hmac);
        } catch (JsonException) {
            return false;
        }
    }

    private static function signingKey(): string
    {
        $key = config('relay.audit_hmac_key');
        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('Security audit signing key is not configured.');
        }

        return $key;
    }

    private static function sign(
        string $key, string $code, string $severity, ?int $userId,
        ?int $clientId, ?string $ipHmac, string $context, string $timestamp,
    ): string {
        return hash_hmac('sha256', implode('|', [
            $code, $severity, (string) $userId, (string) $clientId,
            $ipHmac ?? '', $context, $timestamp,
        ]), $key);
    }
}
