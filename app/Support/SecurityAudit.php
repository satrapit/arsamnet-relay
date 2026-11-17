<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class SecurityAudit
{
    /**
     * Write only allowlisted, non-sensitive event metadata. Fail closed for privileged writes.
     */
    public static function record(
        string $code,
        string $severity,
        ?int $actorUserId,
        array $context = [],
        ?int $clientId = null
    ): void {
        $key = config('relay.audit_hmac_key');
        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('Security audit signing key is not configured.');
        }

        $allowed = ['flag_key', 'old_state', 'new_state', 'role_code', 'target_user_id', 'scope'];
        $safe = array_intersect_key($context, array_flip($allowed));
        $created = now()->utc();
        $record = [
            'client_id' => $clientId,
            'actor_user_id' => $actorUserId,
            'event_code' => $code,
            'severity' => $severity,
            'source_ip_hmac' => null,
            'context_json' => json_encode($safe, JSON_THROW_ON_ERROR),
            'created_at' => $created,
        ];
        $record['record_hmac'] = hash_hmac('sha256', implode('|', [
            $record['event_code'], $record['severity'], (string) $actorUserId,
            (string) $clientId, $record['context_json'], $created->toIso8601String(),
        ]), $key);

        DB::table('security_events')->insert($record);
    }
}
