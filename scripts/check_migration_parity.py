#!/usr/bin/env python3
"""Offline table/column presence drift check. Not a DBML or MySQL type/constraint validator."""
from __future__ import annotations

import argparse
from pathlib import Path
import re

DBML_TABLE = re.compile(r"^Table\s+(\w+)\s*\{\s*\n([\s\S]*?)^\}", re.M)
DBML_COLUMN = re.compile(r"^  ([a-z][a-z0-9_]*)\s+(?:bigint|int|smallint|tinyint|boolean|datetime|char\(\d+\)|varchar\(\d+\)|longtext|text|json)(?:\s|$)", re.M)
MIGRATION_TABLE = re.compile(
    r"Schema::create\(['\"]([^'\"]+)['\"],\s*function\s*\(\s*Blueprint\s+\$table\s*\)\s*:\s*void\s*\{([\s\S]*?)^\s{8}\}\);",
    re.M,
)
FIELD = re.compile(r"^\s*\$table->([A-Za-z]\w*)\(([^)]*)\)", re.M)
COLUMN_METHODS_EXCLUDED = {
    "primary", "unique", "index", "foreign", "fullText", "spatialIndex",
    "dropColumn", "dropForeign", "dropIndex", "dropUnique", "comment", "engine",
}


def tables_from_dbml(content: str) -> dict[str, list[str]]:
    tables: dict[str, list[str]] = {}
    for match in DBML_TABLE.finditer(content):
        name = match.group(1)
        if name in tables:
            raise ValueError(f"Duplicate DBML table {name}")
        columns = DBML_COLUMN.findall(match.group(2))
        if len(columns) != len(set(columns)):
            raise ValueError(f"Duplicate DBML columns in {name}")
        tables[name] = columns
    return tables


def tables_from_migrations(content: str) -> dict[str, list[str]]:
    if "Schema::table(" in content:
        raise ValueError("Schema::table alterations are unsupported by this simple checker; review manually")
    tables: dict[str, list[str]] = {}
    matches = list(MIGRATION_TABLE.finditer(content))
    if len(matches) != len(re.findall(r"Schema::create\(", content)):
        raise ValueError("Unrecognized Schema::create syntax; expand the parser before trusting its output")
    for match in matches:
        name = match.group(1)
        if name in tables:
            raise ValueError(f"Duplicate migration table {name}")
        columns: list[str] = []
        for field in FIELD.finditer(match.group(2)):
            method, args = field.groups()
            if method in COLUMN_METHODS_EXCLUDED:
                continue
            if method == "rememberToken":
                columns.append("remember_token")
                continue
            if method == "timestamps":
                columns.extend(("created_at", "updated_at"))
                continue
            arg = re.match(r"['\"]([^'\"]+)['\"]", args)
            if method == "id":
                columns.append(arg.group(1) if arg else "id")
            elif arg:
                columns.append(arg.group(1))
            else:
                raise ValueError(f"Unsupported field method {method} in {name}")
        if len(columns) != len(set(columns)):
            raise ValueError(f"Duplicate migration columns in {name}")
        tables[name] = columns
    return tables


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parent.parent)
    args = parser.parse_args()
    root = args.root
    schema = tables_from_dbml((root / "docs" / "ERD.dbml").read_text(encoding="utf-8"))
    sources = list((root / "database" / "migrations").glob("*.php"))
    if not sources:
        raise ValueError("No migration files found")
    migrated = tables_from_migrations("\n".join(path.read_text(encoding="utf-8") for path in sorted(sources)))
    changes = []
    for name in sorted(set(schema) | set(migrated)):
        if name not in schema:
            changes.append(f"+ migration-only table: {name}")
        elif name not in migrated:
            changes.append(f"- unmigrated DBML table: {name}")
        else:
            for column in sorted(set(schema[name]) - set(migrated[name])):
                changes.append(f"- unmigrated DBML field: {name}.{column}")
            for column in sorted(set(migrated[name]) - set(schema[name])):
                changes.append(f"+ migration-only field: {name}.{column}")
    if changes:
        print("FAILED — table/column drift:\n" + "\n".join(changes))
        return 1
    print(f"PASS: {len(schema)} matching tables / {sum(map(len, schema.values()))} matching field names")
    print("NOT TESTED: types, nullable/defaults, composite FKs, DBML parser, SQL execution or rollback")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
