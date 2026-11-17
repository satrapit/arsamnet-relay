<?php

require_once dirname(__DIR__).'/app/Support/SchemaManifest.php';
require_once dirname(__DIR__).'/app/Support/OfflineDesignChecks.php';

use App\Support\OfflineDesignChecks;

try {
    $result = OfflineDesignChecks::migrationReferences(dirname(__DIR__));
    printf('PASS: all %d ERD foreign keys match the PHP migration declarations'.PHP_EOL,
        $result['references']);
    echo 'NOT TESTED: actual MySQL constraints or cross-tenant runtime behavior'.PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.$e->getMessage().PHP_EOL);
    exit(1);
}
