<?php

/**
 * Read-only staging readiness gate. No migrations, writes or provider HTTP.
 *
 * Usage: php scripts/staging_preflight.php --expect-db=YOUR_STAGING_DB
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function failed(string $reason): never
{
    fwrite(STDERR, "FAIL: ".$reason."\n");
    exit(1);
}

function passed(string $label): void
{
    fwrite(STDOUT, "PASS: ".$label."\n");
}

try {
    if (count($argv) !== 2 || ! preg_match('/\A--expect-db=([A-Za-z][A-Za-z0-9_]{2,63})\z/D', $argv[1], $match)) {
        failed('Use --expect-db=EXACT_STAGING_DATABASE (never your production database).');
    }

    if (! $app->environment('staging')) {
        failed('APP_ENV must be exactly staging.');
    }
    passed('Explicit staging environment');

    if ((bool) config('app.debug')) {
        failed('APP_DEBUG must be false.');
    }
    passed('Debug disabled');

    $url = (string) config('app.url');
    $parsed = parse_url($url);
    if (! is_array($parsed) || ($parsed['scheme'] ?? '') !== 'https'
        || ! isset($parsed['host'])
        || in_array(strtolower($parsed['host']), ['localhost', '127.0.0.1'], true)
        || isset($parsed['user']) || isset($parsed['pass'])
        || isset($parsed['query']) || isset($parsed['fragment'])) {
        failed('APP_URL must be a public HTTPS origin without URL credentials or query parameters.');
    }
    passed('HTTPS application URL');

    if (DB::getDefaultConnection() !== 'mysql') {
        failed('DB_CONNECTION must be mysql.');
    }

    $name = (string) DB::selectOne('SELECT DATABASE() AS name')->name;
    if (! hash_equals($match[1], $name)) {
        failed('Actual MySQL database does not match --expect-db. No changes made.');
    }
    passed('Exact staged MySQL database matched');

    $version = strtolower((string) DB::selectOne('SELECT VERSION() AS version')->version);
    if (! str_starts_with($version, '8.') || str_contains($version, 'mariadb')) {
        failed('A MySQL 8 server is required.');
    }
    passed('MySQL 8 verified');

    if (! Schema::hasTable('migrations')
        || ! Schema::hasTable('clients')
        || ! Schema::hasTable('deliveries')
        || ! Schema::hasTable('delivery_attempts')
        || ! Schema::hasTable('provider_accounts')
        || ! Schema::hasTable('features')
        || ! Schema::hasTable('security_events')) {
        failed('Required migrated tables are missing; no schema changes attempted.');
    }
    if (DB::table('migrations')->count() < 4) {
        failed('Expected framework/application migrations are not recorded.');
    }
    passed('Core schema is already migrated');

    if (! is_string(config('relay.audit_hmac_key'))
        || strlen((string) config('relay.audit_hmac_key')) < 32) {
        failed('Audit signing secret is not set correctly.');
    }
    if (! is_string(config('app.key')) || strlen((string) config('app.key')) < 32) {
        failed('Application encryption key is absent.');
    }
    passed('Application encryption and audit signing configured');

    // Fail closed. Super Admin may enable a one-recipient trial separately
    // after this preflight and after staging identity/egress review.
    if (Feature::globally()->active('message-publishing')
        || Feature::globally()->active('kavenegar-sending')
        || Feature::globally()->active('provider-status-queries')) {
        failed('At least one network/publish flag is already enabled. Disable it in Super Admin before preflight.');
    }
    passed('Publishing, Kavenegar sending and provider-status network gates are OFF');

    echo "READY: Read-only staging prerequisites passed. No SMS sent and no data changed.\n";
    echo "NEXT: Inspect tenant/route and egress logging; then run one explicit operator-controlled trial.\n";
} catch (Throwable) {
    failed('Preflight encountered an environment or database error. Inspect staging configuration privately.');
}
