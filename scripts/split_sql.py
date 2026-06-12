#!/usr/bin/env python3
"""Split winsumwebfinal (9).sql into sql_import/ for phpMyAdmin batch import."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "winsumwebfinal (9).sql"
OUT = ROOT / "sql_import"
DATA_DIR = OUT / "03_data"

INSERT_RE = re.compile(r"^INSERT INTO `([^`]+)`", re.I)


def strip_comments(lines: list[str]) -> list[str]:
    return [ln for ln in lines if not ln.lstrip().startswith("--")]


def read_until_semicolon(lines: list[str], start: int) -> tuple[str, int]:
    chunk = [lines[start]]
    i = start + 1
    while i < len(lines) and not chunk[-1].strip().endswith(";"):
        chunk.append(lines[i])
        i += 1
    return "".join(chunk), i


def read_alter_block(lines: list[str], start: int) -> tuple[str, int]:
    chunk = [lines[start]]
    i = start + 1
    while i < len(lines):
        chunk.append(lines[i])
        if lines[i].strip().endswith(";"):
            break
        i += 1
    return "".join(chunk), i + 1


def main() -> None:
    raw = SOURCE.read_text(encoding="utf-8").splitlines(keepends=True)
    lines = strip_comments(raw)

    setup: list[str] = []
    tables: list[str] = []
    inserts: dict[str, list[str]] = {}
    indexes: list[str] = []
    auto_inc: list[str] = []
    foreign: list[str] = []
    finalize: list[str] = []

    i = 0
    while i < len(lines):
        stripped = lines[i].strip()

        if not setup and stripped.startswith("CREATE TABLE"):
            pass
        elif not tables and (
            stripped.startswith("SET ")
            or stripped.startswith("START TRANSACTION")
            or stripped.startswith("/*!40101 SET")
        ):
            setup.append(lines[i])
            i += 1
            continue

        if stripped.startswith("CREATE TABLE"):
            block, i = read_until_semicolon(lines, i)
            tables.append(block)
            continue

        if stripped.startswith("INSERT INTO"):
            block, i = read_until_semicolon(lines, i)
            m = INSERT_RE.match(block.strip().split("\n", 1)[0].strip())
            if m:
                inserts.setdefault(m.group(1), []).append(block)
            continue

        if stripped.startswith("ALTER TABLE"):
            block, i = read_alter_block(lines, i)
            if "ADD PRIMARY KEY" in block:
                indexes.append(block)
            elif "MODIFY `" in block and "AUTO_INCREMENT" in block:
                auto_inc.append(block)
            elif "ADD CONSTRAINT" in block:
                foreign.append(block)
            continue

        if stripped.startswith("COMMIT") or stripped.startswith("/*!40101 SET CHARACTER_SET") or stripped.startswith("/*!40101 SET COLLATION"):
            block, i = read_until_semicolon(lines, i)
            finalize.append(block)
            continue

        i += 1

    OUT.mkdir(exist_ok=True)
    DATA_DIR.mkdir(exist_ok=True)

    (OUT / "01_setup.sql").write_text("".join(setup), encoding="utf-8")
    (OUT / "02_tables.sql").write_text("".join(tables), encoding="utf-8")
    (OUT / "04_indexes.sql").write_text("\n".join(indexes) + ("\n" if indexes else ""), encoding="utf-8")
    (OUT / "05_foreign_keys.sql").write_text("\n".join(foreign) + ("\n" if foreign else ""), encoding="utf-8")
    (OUT / "06_finalize.sql").write_text("\n".join(auto_inc) + ("\n" if auto_inc else ""), encoding="utf-8")
    (OUT / "07_migrations.sql").write_text("", encoding="utf-8")
    (OUT / "08_commit.sql").write_text("".join(finalize), encoding="utf-8")

    order = [
        "blog_categories",
        "blog_posts",
        "brands",
        "categories",
        "coupons",
        "coupon_redemptions",
        "customers",
        "inventory_alerts",
        "inventory_items",
        "orders",
        "order_items",
        "order_payments",
        "order_shipments",
        "payment_methods",
        "products",
        "product_images",
        "product_reviews",
        "shipping_methods",
        "warehouses",
    ]
    idx = 1
    data_files: list[str] = []
    for table in order:
        if table not in inserts:
            continue
        name = f"{idx:02d}_{table}.sql"
        (DATA_DIR / name).write_text("".join(inserts[table]), encoding="utf-8")
        data_files.append(f"03_data\\{name}")
        idx += 1

    bat_lines = [
        "@echo off",
        "setlocal",
        "set MYSQL=mysql -u root winsumwebfinal",
        "echo Import winsumwebfinal (9) split files...",
        "%MYSQL% < 01_setup.sql",
        "%MYSQL% < 02_tables.sql",
    ]
    for df in data_files:
        bat_lines.append(f"%MYSQL% < {df}")
    bat_lines += [
        "%MYSQL% < 04_indexes.sql",
        "%MYSQL% < 05_foreign_keys.sql",
        "%MYSQL% < 06_finalize.sql",
        "%MYSQL% < 08_commit.sql",
        "echo Done.",
        "pause",
    ]
    (OUT / "import_all.bat").write_text("\r\n".join(bat_lines) + "\r\n", encoding="utf-8")

    readme = """# sql_import

Tách từ `winsumwebfinal (9).sql` — **21 bảng** (không có `banners`, `order_status_histories`).

## Thứ tự import

1. Tạo database `winsumwebfinal` (utf8mb4)
2. Chạy `import_all.bat` hoặc import lần lượt:
   - `01_setup.sql` → `02_tables.sql` → `03_data/*.sql` → `04_indexes.sql` → `05_foreign_keys.sql` → `06_finalize.sql` → `08_commit.sql`

## Tái tạo bộ file

```bash
python scripts/split_sql.py
```
"""
    (OUT / "README.md").write_text(readme, encoding="utf-8")
    print(f"Created sql_import: {len(tables)} tables, {len(data_files)} data files, {len(indexes)} index blocks")


if __name__ == "__main__":
    main()
