<?php

require_once dirname(__DIR__).'/app/Support/SchemaManifest.php';
require_once dirname(__DIR__).'/app/Support/OfflineDesignChecks.php';

use App\Support\OfflineDesignChecks;

try {
    $result = OfflineDesignChecks::design(dirname(__DIR__));
    printf('PASS: %d tables, %d columns, %d relationships, %d CSV samples'.PHP_EOL,
        $result['tables'], $result['fields'], $result['refs'], $result['samples']);
    echo 'NOT TESTED: official DBML parser or MySQL runtime behavior'.PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.$e->getMessage().PHP_EOL);
    exit(1);
}
