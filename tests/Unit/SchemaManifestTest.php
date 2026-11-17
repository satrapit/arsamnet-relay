<?php

namespace Tests\Unit;

use App\Support\SchemaManifest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SchemaManifestTest extends TestCase
{
    public function test_project_erd_parses_all_tables_columns_and_references(): void
    {
        $design = SchemaManifest::parse((string) file_get_contents(dirname(__DIR__, 2).'/docs/ERD.dbml'));

        $this->assertCount(27, $design['tables']);
        $this->assertSame(192, array_sum(array_map('count', $design['tables'])));
        $this->assertCount(30, $design['references']);
        $this->assertContains(
            'deliveries(message_id,client_id)->messages(id,client_id)',
            $design['references']
        );
        $this->assertContains(
            ['client_id', 'idempotency_key_hash'],
            $design['unique_indexes']['messages']
        );
        $this->assertTrue($design['tables']['users']['email']['required']);
    }

    public function test_missing_reference_fails_closed(): void
    {
        $valid = (string) file_get_contents(dirname(__DIR__, 2).'/docs/ERD.dbml');
        $broken = preg_replace('/^Ref:.+\R/m', '', $valid, 1);
        $this->expectException(InvalidArgumentException::class);
        SchemaManifest::parse((string) $broken);
    }
}
