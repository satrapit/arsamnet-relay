<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SecurityAudit
{
    /**
     * Persist a redacted event and a keyed integrity tag in the same DB transaction.
     * Sensitive request data must never be passed through $context.
     */
    public static function record(
        string $code,
        string $severity,
        ?int $actorUserId,
        array $context = [],
        ?int $clientId = null,
        ?string $sourceIp = null,
    ): void {
        $key = config('relay.audit_hmac_key');
        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('Security audit signing key is not configured.');
        }

        $allowed = [
            'flag_key', 'old_state', 'new_state', 'role_code',
            'target_user_id', 'scope', 'client_id', 'key_id',
        ];
        $safe = array_intersect_key($context, array_flip($allowed));
        $created = now()->utc();
        $ipHmac = $sourceIp ? hash_hmac('sha256', $sourceIp, $key) : null;
        $record = [
            'client_id' => $clientId,
            'actor_user_id' => $actorUserId,
            'event_code' => $code,
            'severity' => $severity,
            'source_ip_hmac' => $ipHmac,
            'context_json' => json_encode($safe, JSON_THROW_ON_ERROR),
            'created_at' => $created,
        ];
        $record['record_hmac'] = hash_hmac('sha256', implode('|', [
            $code, $severity, (string) $actorUserId, (string) $clientId,
            $ipHmac ?? '', $record['context_json'], $created->format('Y-m-d\TH:i:s\Z'),
        ]), $key);

        DB::table('security_events')->insert($record);
    }
}
