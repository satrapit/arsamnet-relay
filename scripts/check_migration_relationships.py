#!/usr/bin/env python3
"""Compare ERD DBML restricted foreign keys with the initial PHP migration sources.

This strict source-level check recognizes only the foreignId()->constrained()
and foreign()->references()->on() patterns currently used by this project.
It deliberately fails when a new FK syntax needs explicit parser support.
It does not execute SQL or prove the actual MySQL constraints.
"""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parent.parent
SCHEMA = ROOT / "docs" / "ERD.dbml"
MIGRATIONS = ROOT / "database" / "migrations"

TABLE = re.compile(
    r"Schema::create\('([a-z_]+)', function \(Blueprint \$table\): void \{([\s\S]*?)^        \}\);",
    re.M,
)
IMPLICIT = re.compile(
    r"\$table->foreignId\('([a-z_]+)'\)(?:->nullable\(\))?"
    r"->constrained\('([a-z_]+)'\)->restrictOnDelete\(\)"
)
EXPLICIT = re.compile(
    r"\$table->foreign\((.*?)\)->references\((.*?)\)"
    r"->on\('([a-z_]+)'\)->restrictOnDelete\(\)"
)
DBML_REF = re.compile(
    r"^Ref:\s*([a-z_]+)\.(\([a-z_, ]+\)|[a-z_]+)\s*>\s*"
    r"([a-z_]+)\.(\([a-z_, ]+\)|[a-z_]+)\s*"
    r"\[delete:\s*restrict\]\s*$",
    re.M,
)


def names(expr: str) -> list[str]:
    if expr.startswith("["):
        found = re.findall(r"'([a-z_]+)'", expr)
        if not found or not re.fullmatch(r"\[\s*'[a-z_]+'(?:\s*,\s*'[a-z_]+')*\s*\]", expr):
            raise ValueError(f"Unsupported PHP column list: {expr}")
        return found
    if re.fullmatch(r"'[a-z_]+'", expr):
        return [expr[1:-1]]
    raise ValueError(f"Unsupported PHP foreign key columns: {expr}")


def ref_key(child: str, child_cols: list[str], parent: str, parent_cols: list[str]) -> str:
    if len(child_cols) != len(parent_cols):
        raise ValueError("FK column count mismatch")
    return f"{child}({','.join(child_cols)})->{parent}({','.join(parent_cols)})"


def expected() -> set[str]:
    source = SCHEMA.read_text(encoding="utf-8")
    raw_refs = [line for line in source.splitlines() if line.startswith("Ref:")]
    result = set()
    for line in raw_refs:
        found = DBML_REF.fullmatch(line)
        if not found:
            raise ValueError(f"Unsupported DBML reference: {line}")
        child, child_cols, parent, parent_cols = found.groups()
        result.add(ref_key(child, child_cols.strip("()").replace(" ", "").split(","),
                           parent, parent_cols.strip("()").replace(" ", "").split(",")))
    if len(result) != len(raw_refs):
        raise ValueError("Duplicate DBML references")
    return result


def from_migrations() -> set[str]:
    files = sorted(MIGRATIONS.glob("*.php"))
    if not files:
        raise ValueError("Missing migration files")
    sources = "\n".join(path.read_text(encoding="utf-8") for path in files)
    tables = list(TABLE.finditer(sources))
    if len(tables) != sources.count("Schema::create("):
        raise ValueError("Unrecognized Schema::create syntax")
    refs: list[str] = []
    for table in tables:
        child = table.group(1)
        for line in table.group(2).splitlines():
            if "->constrained(" not in line and "$table->foreign(" not in line:
                continue
            implicit = IMPLICIT.search(line)
            explicit = EXPLICIT.search(line)
            if implicit is not None:
                refs.append(ref_key(child, [implicit.group(1)], implicit.group(2), ["id"]))
            elif explicit is not None:
                refs.append(ref_key(child, names(explicit.group(1)),
                                    explicit.group(3), names(explicit.group(2))))
            else:
                raise ValueError(f"Unsupported migration FK declaration in {child}: {line.strip()}")
    if len(refs) != len(set(refs)):
        raise ValueError("Duplicate migration foreign keys")
    return set(refs)


def main() -> int:
    design = expected()
    actual = from_migrations()
    missing = sorted(design - actual)
    extra = sorted(actual - design)
    if len(design) != 32 or missing or extra:
        print(f"FAIL: {len(design)} documented versus {len(actual)} migrated foreign keys")
        for ref in missing:
            print("ERD-only: " + ref)
        for ref in extra:
            print("Migration-only: " + ref)
        return 1
    print(f"PASS: all {len(design)} ERD foreign keys match the PHP migration declarations")
    print("NOT TESTED: actual MySQL constraints, on-disk table state, or cross-tenant runtime behavior")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
