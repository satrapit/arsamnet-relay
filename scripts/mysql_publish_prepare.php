<?php

/**
 * Explicitly create one new, separately named MySQL integration schema.
 * Never selects, alters, migrates, truncates or drops an existing schema.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$expected = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--create=')) {
        $expected = substr($argument, 9);
    } else {
        fwrite(STDERR, "ERROR: Only --create=arsamnet_relay_integration_test is supported.\n");
        exit(1);
    }
}

if ($expected !== 'arsamnet_relay_integration_test'
    || ! $app->environment('local')
    || $app->configurationIsCached()
    || DB::getDefaultConnection() !== 'mysql') {
    fwrite(STDERR, "REFUSED: exact isolated schema name, local env, uncached config and MySQL are required.\n");
    exit(1);
}

try {
    $connected = (string) (DB::selectOne('SELECT DATABASE() AS selected_schema')->selected_schema ?? '');
    $version = (string) (DB::selectOne('SELECT VERSION() AS version')->version ?? '');
    if ($connected !== 'arsamnet_relay' || str_contains(strtolower($version), 'mariadb')
        || ! str_starts_with($version, '8.')) {
        throw new RuntimeException('Expected local primary arsamnet_relay schema on MySQL 8.');
    }

    $alreadyExists = DB::selectOne(
        'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
        [$expected]
    );
    if ($alreadyExists) {
        throw new RuntimeException('Integration schema already exists; refusing to change it.');
    }

    DB::statement('CREATE DATABASE arsamnet_relay_integration_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "CREATED: arsamnet_relay_integration_test (empty isolated schema).\n";
    echo "No migrations or tests were executed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'REFUSED: '.$e->getMessage().PHP_EOL);
    exit(1);
}
