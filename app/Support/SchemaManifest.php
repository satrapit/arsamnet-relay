<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Minimal, strict parser for the supported ERD.dbml subset.
 *
 * This is intentionally not a general-purpose DBML parser. Unknown column,
 * index or reference declarations fail closed instead of being skipped.
 */
final class SchemaManifest
{
    /**
     * @return array{tables: array<string, array<string, array{type: string, length: ?int, required: bool}>>, references: list<string>, unique_indexes: array<string, list<list<string>>>, ordinary_indexes: array<string, list<list<string>>>}
     */
    public static function parse(string $dbml): array
    {
        $tables = [];
        $references = [];
        $indexes = [];
        $ordinaryIndexes = [];

        preg_match_all('/^Table ([a-z_]+) \{([\s\S]*?)^\}/m', $dbml, $blocks, PREG_SET_ORDER);

        foreach ($blocks as $block) {
            $table = $block[1];
            if (isset($tables[$table])) {
                throw new InvalidArgumentException('Duplicate ERD table: '.$table);
            }

            $tables[$table] = [];
            $indexes[$table] = [];
            $ordinaryIndexes[$table] = [];

            foreach (preg_split('/\R/', $block[2]) as $line) {
                if (trim($line) === '' || trim($line) === 'indexes {' || trim($line) === '}') {
                    continue;
                }
                if (preg_match('/^  ([a-z_]+) ([a-z]+)(?:\((\d+)\))? \[([^\]]+)\]\s*$/', $line, $field)) {
                    $name = $field[1];
                    if (isset($tables[$table][$name])) {
                        throw new InvalidArgumentException('Duplicate ERD field: '.$table.'.'.$name);
                    }

                    $flags = explode('note:', $field[4], 2)[0];
                    $isPrimary = (bool) preg_match('/(?:^|,\s*)pk(?:,|$)/', $flags);
                    $isUnique = (bool) preg_match('/(?:^|,\s*)unique(?:,|$)/', $flags);
                    $tables[$table][$name] = [
                        'type' => $field[2],
                        'length' => isset($field[3]) && $field[3] !== '' ? (int) $field[3] : null,
                        'required' => $isPrimary || str_contains($flags, 'not null'),
                    ];
                    if ($isPrimary || $isUnique) {
                        $indexes[$table][] = [$name];
                    }

                    continue;
                }

                if (preg_match('/^    (?:\(([a-z_, ]+)\)|([a-z_]+))(?: \[(pk|unique)\])?\s*$/', $line, $index)) {
                    $columns = array_map('trim', explode(',', $index[1] !== '' ? $index[1] : $index[2]));
                    if (! empty($index[3])) {
                        $indexes[$table][] = $columns;
                    } else {
                        $ordinaryIndexes[$table][] = $columns;
                    }

                    continue;
                }

                throw new InvalidArgumentException('Unsupported ERD declaration in '.$table.': '.trim($line));
            }
            if ($tables[$table] === []) {
                throw new InvalidArgumentException('No fields in ERD table '.$table);
            }
        }

        foreach (preg_split('/\R/', $dbml) as $line) {
            if (! str_starts_with($line, 'Ref:')) {
                continue;
            }
            if (! preg_match('/^Ref:\s*([a-z_]+)\.(\([a-z_, ]+\)|[a-z_]+)\s*>\s*([a-z_]+)\.(\([a-z_, ]+\)|[a-z_]+)\s*\[delete:\s*restrict\]\s*$/', $line, $match)) {
                throw new InvalidArgumentException('Unsupported ERD foreign key: '.$line);
            }
            $childCols = self::columns($match[2]);
            $parentCols = self::columns($match[4]);
            if (count($childCols) !== count($parentCols)) {
                throw new InvalidArgumentException('Mismatched foreign key columns.');
            }
            $references[] = self::referenceKey($match[1], $childCols, $match[3], $parentCols);
        }

        if (count($tables) !== 27 || array_sum(array_map('count', $tables)) !== 192 || count($references) !== 32) {
            throw new InvalidArgumentException('ERD manifest must contain 27 tables, 192 fields and 32 references.');
        }

        return [
            'tables' => $tables,
            'references' => $references,
            'unique_indexes' => $indexes,
            'ordinary_indexes' => $ordinaryIndexes,
        ];
    }

    /** @return list<string> */
    private static function columns(string $value): array
    {
        return array_map('trim', explode(',', trim($value, '()')));
    }

    /** @param list<string> $childColumns @param list<string> $parentColumns */
    public static function referenceKey(string $child, array $childColumns, string $parent, array $parentColumns): string
    {
        return $child.'('.implode(',', $childColumns).')->'.$parent.'('.implode(',', $parentColumns).')';
    }
}
