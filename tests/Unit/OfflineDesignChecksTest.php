<?php

namespace Tests\Unit;

use App\Support\OfflineDesignChecks;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OfflineDesignChecksTest extends TestCase
{
    public function test_design_csv_and_samples_match_the_documented_erd(): void
    {
        $result = OfflineDesignChecks::design(dirname(__DIR__, 2));
        $this->assertSame(['tables' => 27, 'fields' => 192, 'refs' => 32, 'samples' => 27], $result);
    }

    public function test_all_migration_table_and_column_names_match_the_design(): void
    {
        $this->assertSame(['tables' => 27, 'fields' => 192],
            OfflineDesignChecks::migrationColumns(dirname(__DIR__, 2)));
    }

    public function test_all_migration_foreign_keys_match_the_design(): void
    {
        $this->assertSame(['references' => 32],
            OfflineDesignChecks::migrationReferences(dirname(__DIR__, 2)));
    }

    public function test_parser_rejects_unrecognized_schema_alterations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OfflineDesignChecks::parseMigrationTables("Schema::table('users', function () {});");
    }

    public function test_parser_rejects_unsupported_foreign_key_syntax(): void
    {
        $source = <<<'MIGRATION'
Schema::create('examples', function (Blueprint $table): void {
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
        });
MIGRATION;
        $this->expectException(InvalidArgumentException::class);
        OfflineDesignChecks::parseMigrationReferences($source);
    }
}
