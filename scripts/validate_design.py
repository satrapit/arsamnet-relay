#!/usr/bin/env python3
"""Offline structural checks; not an official DBML grammar or MySQL test."""
from pathlib import Path
import csv
import re

root = Path(__file__).resolve().parent.parent
dbml = (root / "docs" / "ERD.dbml").read_text(encoding="utf-8")
tables = {}
current = None
for line in dbml.splitlines():
    match = re.match(r"^Table ([a-z][a-z0-9_]*) \{", line)
    if match:
        current = match.group(1)
        if current in tables:
            raise AssertionError("Duplicate table " + current)
        tables[current] = []
        continue
    if current:
        column = re.match(
            r"^  ([a-z][a-z0-9_]*) (?:bigint|int|tinyint|smallint|boolean|datetime|char\(\d+\)|varchar\(\d+\)|longtext|text|json)(?: |$)",
            line,
        )
        if column:
            tables[current].append(column.group(1))
            assert len(tables[current]) == len(set(tables[current]))

refs = re.findall(r"^Ref: ([\w.(), ]+) > ([\w.(), ]+) \[delete: restrict\]$", dbml, re.M)
for left, right in refs:
    for item in (left, right):
        table, col_part = item.split(".", 1)
        for col in col_part.strip("()").split(", "):
            assert table in tables and col in tables[table], item

with (root / "docs" / "ERD.csv").open(newline="", encoding="utf-8") as stream:
    dictionary = list(csv.DictReader(stream))
assert len(dictionary) == sum(map(len, tables.values()))
for row in dictionary:
    assert row["table"] in tables
    assert row["column"] in tables[row["table"]]

samples = list((root / "docs" / "dbdiagram-csv").glob("*.csv"))
assert len(samples) == len(tables)
for name, columns in tables.items():
    with (root / "docs" / "dbdiagram-csv" / (name + ".csv")).open(newline="", encoding="utf-8") as stream:
        rows = list(csv.reader(stream))
    assert len(rows) == 2 and rows[0] == columns
    assert len(rows[1]) == len(columns)

assert len(tables) == 27 and len(dictionary) == 192 and len(refs) == 32
print(f"PASS: {len(tables)} tables, {len(dictionary)} columns, {len(refs)} relationships, {len(samples)} CSV samples")
print("NOT TESTED: DBML official parser, MySQL migration parity, Laravel/Fortify/Pennant runtime")
