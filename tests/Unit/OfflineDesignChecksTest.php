<?php

namespace Tests\Unit;

use App\Support\OfflineDesignChecks;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OfflineDesignChecksTest extends TestCase
{
    public function test_single_dbml_file_matches_the_documented_erd(): void
    {
        $result = OfflineDesignChecks::design(dirname(__DIR__, 2));
        $this->assertSame(['tables' => 27, 'fields' => 192, 'refs' => 32], $result);
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

    public function test_parser_accepts_scalar_explicit_foreign_key_columns(): void
    {
        $source = <<<'MIGRATION'
Schema::create('examples', function (Blueprint $table): void {
            $table->foreign('delivery_id')->references('id')->on('deliveries')->restrictOnDelete();
        });
MIGRATION;

        $this->assertSame(
            ['examples(delivery_id)->deliveries(id)'],
            OfflineDesignChecks::parseMigrationReferences($source)
        );
    }

    public function test_parser_accepts_composite_explicit_foreign_key_columns(): void
    {
        $source = <<<'MIGRATION'
Schema::create('examples', function (Blueprint $table): void {
            $table->foreign(['message_id', 'client_id'])->references(['id', 'client_id'])->on('messages')->restrictOnDelete();
        });
MIGRATION;

        $this->assertSame(
            ['examples(message_id,client_id)->messages(id,client_id)'],
            OfflineDesignChecks::parseMigrationReferences($source)
        );
    }

    public function test_parser_rejects_dynamic_explicit_fk_columns(): void
    {
        $source = <<<'MIGRATION'
Schema::create('examples', function (Blueprint $table): void {
            $table->foreign($column)->references('id')->on('deliveries')->restrictOnDelete();
        });
MIGRATION;

        $this->expectException(InvalidArgumentException::class);
        OfflineDesignChecks::parseMigrationReferences($source);
    }
}
