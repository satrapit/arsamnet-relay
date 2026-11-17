<?php

/**
 * Minimal dependency-free smoke test for the real SecurityAudit class.
 * This is deliberately NOT a substitute for framework/DB integration testing.
 */

namespace Illuminate\Support {
    class Carbon extends \DateTimeImmutable
    {
        public function utc(): self
        {
            return $this;
        }

        public static function parse(string $date, string $timezone = 'UTC'): self
        {
            return new self($date, new \DateTimeZone($timezone));
        }
    }
}

namespace Illuminate\Support\Facades {
    class DB
    {
        public static array $rows = [];

        public static function table(string $name): object
        {
            if ($name !== 'security_events') {
                throw new \RuntimeException('wrong table');
            }

            return new class
            {
                public function insert(array $row): void
                {
                    $row['created_at'] = $row['created_at']->format('Y-m-d H:i:s');
                    $row['context_json'] = json_encode(json_decode($row['context_json']), JSON_UNESCAPED_SLASHES);
                    DB::$rows[] = (object) ($row + ['id' => count(DB::$rows) + 1]);
                }
            };
        }
    }
}

namespace App\Support {
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\DB;

    function now(): Carbon
    {
        return new Carbon('2026-11-17 14:00:00', new \DateTimeZone('UTC'));
    }
    function config(string $name): string
    {
        return $name === 'relay.audit_hmac_key' ? str_repeat('a', 64) : '';
    }
    require dirname(__DIR__, 2).'/app/Support/SecurityAudit.php';

    SecurityAudit::record('feature_flag.changed', 'high', 42, [
        'new_state' => true, 'flag_key' => 'webhook-delivery',
        'password' => 'must-not-be-saved',
    ], 4, '192.0.2.1');

    $row = DB::$rows[0];
    if (! SecurityAudit::verifyRecord($row)) {
        throw new \RuntimeException('Should verify');
    }
    if (str_contains($row->context_json, 'password') || str_contains($row->context_json, 'must-not-be-saved')) {
        throw new \RuntimeException('Sensitive metadata leaked');
    }
    if (str_contains($row->source_ip_hmac, '192.0.2.1') || strlen($row->source_ip_hmac) !== 64) {
        throw new \RuntimeException('IP not HMACed');
    }
    $row->severity = 'critical';
    if (SecurityAudit::verifyRecord($row)) {
        throw new \RuntimeException('Tamper was not detected');
    }

    SecurityAudit::record('auth.login.failed', 'warning', null, ['password' => 'private']);
    $empty = DB::$rows[1];
    if ($empty->context_json !== '{}') {
        throw new \RuntimeException('Empty context not canonical');
    }
    if (! SecurityAudit::verifyRecord($empty)) {
        throw new \RuntimeException('Empty row did not verify');
    }

    echo "PASS: 6 standalone security audit assertions (stubbed DB / Carbon; no Laravel)\n";
}
