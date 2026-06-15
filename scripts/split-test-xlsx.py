#!/usr/bin/env python3
"""Inspect and split Winsum test case xlsx by module."""
from __future__ import annotations

import re
import shutil
import sys
import zipfile
from pathlib import Path
from xml.etree import ElementTree as ET

NS = {"m": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
NS_R = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
ET.register_namespace("", NS["m"])
ET.register_namespace("r", NS_R)


def read_workbook_sheets(path: Path) -> list[tuple[str, str]]:
    with zipfile.ZipFile(path) as z:
        xml = z.read("xl/workbook.xml").decode("utf-8")
    sheets = []
    for m in re.finditer(r'<sheet name="([^"]+)"[^>]+r:id="([^"]+)"', xml):
        sheets.append((m.group(1), m.group(2)))
    return sheets


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    template = root / "docs" / "Template - Test Case.xlsx"
    filled = root / "Winsum-Test-Case-Template-Filled (1).xlsx"
    out = root / "scripts" / "sheet-list.txt"
    lines = []
    for p in (template, filled):
        lines.append(f"\n=== {p.name} ===")
        for name, rid in read_workbook_sheets(p):
            lines.append(f"  {name} ({rid})")
    out.write_text("\n".join(lines), encoding="utf-8")
    print(str(out))


if __name__ == "__main__":
    main()
