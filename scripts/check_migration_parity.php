<?php

require_once dirname(__DIR__).'/app/Support/SchemaManifest.php';
require_once dirname(__DIR__).'/app/Support/OfflineDesignChecks.php';

use App\Support\OfflineDesignChecks;

try {
    $result = OfflineDesignChecks::migrationColumns(dirname(__DIR__));
    printf('PASS: %d matching tables / %d matching field names'.PHP_EOL,
        $result['tables'], $result['fields']);
    echo 'NOT TESTED: defaults, nullability, SQL execution or rollback'.PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.$e->getMessage().PHP_EOL);
    exit(1);
}
