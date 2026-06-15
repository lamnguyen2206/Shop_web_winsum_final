#!/usr/bin/env python3
"""Generate Winsum test case xlsx with one sheet per module."""
from __future__ import annotations

import json
import re
import shutil
import subprocess
import sys
import zipfile
from copy import deepcopy
from pathlib import Path
from xml.etree import ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
PHP = Path(r"C:\xampp\php\php.exe")
DATA_PHP = ROOT / "docs" / "testing" / "test-report-data.php"
TEMPLATE_FILLED = ROOT / "Winsum-Test-Case-Template-Filled (1).xlsx"
OUTPUT = TEMPLATE_FILLED
PROJECT_ITEM = "Shop web Winsum Testing"


def load_report_data() -> dict:
    cmd = [str(PHP), "-r", f"echo json_encode(require '{DATA_PHP.as_posix()}', JSON_UNESCAPED_UNICODE);"]
    raw = subprocess.check_output(cmd, cwd=ROOT, text=True, encoding="utf-8")
    return json.loads(raw)


def derive_precondition(code: str) -> str:
    if code.startswith("TC-D") or code.startswith("TC-E"):
        return "Đã có tài khoản khách hàng và/hoặc đơn hàng mẫu trong hệ thống"
    if code.startswith(("TC-H", "TC-I", "TC-J", "TC-K")):
        return "Có tài khoản admin; đăng nhập admin@winsumhome.vn thành công"
    if code.startswith("TC-N"):
        return "Đơn hàng đã giao (delivered) trong vòng 7 ngày; dữ liệu mẫu sẵn sàng"
    if code.startswith(("TC-C", "TC-B")):
        return "Có sản phẩm còn hàng trong giỏ hoặc catalog"
    return "Hệ thống Winsum Home đang chạy tại http://localhost/webwinsum"


class SharedStrings:
    def __init__(self, xml: str):
        self.blocks: list[str] = re.findall(r"<si>.*?</si>", xml, flags=re.S)
        self.index: dict[str, int] = {}
        for i, block in enumerate(self.blocks):
            m = re.search(r"<t[^>]*>(.*?)</t>", block, flags=re.S)
            if m:
                val = (
                    m.group(1)
                    .replace("&lt;", "<")
                    .replace("&gt;", ">")
                    .replace("&amp;", "&")
                    .replace("&quot;", '"')
                )
                self.index.setdefault(val, i)

    def add(self, text: str) -> int:
        if text in self.index:
            return self.index[text]
        escaped = (
            text.replace("&", "&amp;")
            .replace("<", "&lt;")
            .replace(">", "&gt;")
            .replace('"', "&quot;")
        )
        block = f'<si><t xml:space="preserve">{escaped}</t></si>'
        self.blocks.append(block)
        idx = len(self.blocks) - 1
        self.index[text] = idx
        return idx

    def to_xml(self) -> str:
        count = len(self.blocks)
        return (
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            f'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="{count}" uniqueCount="{count}">'
            + "".join(self.blocks)
            + "</sst>"
        )


def extract_header_rows(sheet_xml: str) -> dict[int, str]:
    m = re.search(r"<sheetData>(.*?)</sheetData>", sheet_xml, flags=re.S)
    if not m:
        raise RuntimeError("sheetData not found")
    rows = {}
    for rm in re.finditer(r'(<row r="(\d+)"[^>]*>.*?</row>)', m.group(1), flags=re.S):
        num = int(rm.group(2))
        if num <= 8:
            rows[num] = rm.group(1)
    return rows


def patch_tester(header_rows: dict[int, str], tester: str, tester_idx: int) -> dict[int, str]:
    rows = dict(header_rows)
    if 3 in rows:
        rows[3] = re.sub(
            r'<c r="B3"[^>]*>.*?</c>',
            f'<c r="B3" s="151" t="s"><v>{tester_idx}</v></c>',
            rows[3],
            count=1,
        )
    return rows


def patch_total_formula(header_rows: dict[int, str], last_row: int) -> dict[int, str]:
    rows = dict(header_rows)
    if 5 in rows:
        rows[5] = re.sub(r"COUNTA\(A9:A\d+\)", f"COUNTA(A9:A{last_row})", rows[5])
        rows[5] = re.sub(r"<f>([^<]+)</f><v>[^<]*</v>", r"<f>\1</f>", rows[5])
    return rows


def pad_cols(row: int, style: str = "91") -> str:
    cols = []
    for c in range(ord("K"), ord("Z") + 1):
        cols.append(f'<c r="{chr(c)}{row}" s="{style}"/>')
    return "".join(cols)


def build_section_row(row: int, title_idx: int, date_serial: int, tester: str) -> str:
    tester_esc = tester.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
    return (
        f'<row r="{row}" spans="1:26" ht="14.25" customHeight="1" x14ac:dyDescent="0.3">'
        f'<c r="A{row}" s="81"/>'
        f'<c r="B{row}" s="81" t="s"><v>{title_idx}</v></c>'
        f'<c r="C{row}" s="82"/><c r="D{row}" s="82"/><c r="E{row}" s="82"/><c r="F{row}" s="82"/>'
        f'<c r="G{row}" s="86"><v>{date_serial}</v></c>'
        f'<c r="H{row}" s="89" t="str"><f>B3</f><v>{tester_esc}</v></c>'
        f'<c r="I{row}" s="84"/><c r="J{row}" s="85"/>'
        f'{pad_cols(row, "56")}</row>'
    )


def build_case_row(
    row: int,
    case_serial: int,
    case: list[str],
    date_serial: int,
    tester: str,
    ss: SharedStrings,
    pass_idx: int,
    first_case_row: int,
) -> str:
    code, desc, steps, expected = case[0], case[1], case[2], case[3]
    result = case[4] if len(case) > 4 else "Pass"
    pre = derive_precondition(code)

    desc_idx = ss.add(desc)
    pre_idx = ss.add(pre)
    steps_idx = ss.add(steps)
    expected_idx = ss.add(expected)
    note_idx = ss.add(code)
    result_idx = pass_idx if result == "Pass" else ss.add(result)

    id_value = f"[{PROJECT_ITEM}-{case_serial}]"
    id_esc = id_value.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
    tester_esc = tester.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")

    if row == first_case_row:
        blank_count = row - 9
        blank_range = f"$E$8:E{row - 1}" if blank_count else "$E$8:E8"
        id_formula = (
            'IF(AND(E' + str(row) + '=""),"",'
            '"["&amp;TEXT($B$1,"##")&amp;"-"&amp;TEXT(ROW()-9-COUNTBLANK('
            + blank_range
            + ')+1,"##")&amp;"]")'
        )
        a_cell = (
            f'<c r="A{row}" s="86" t="str"><f t="shared" ref="A{first_case_row}:A500" si="0">'
            f"{id_formula}</f><v>{id_esc}</v></c>"
        )
        h_cell = (
            f'<c r="H{row}" s="89" t="str"><f t="shared" ref="H{first_case_row}:H500" si="1">$B$3</f>'
            f"<v>{tester_esc}</v></c>"
        )
    else:
        a_cell = f'<c r="A{row}" s="89" t="str"><f t="shared" si="0"/><v>{id_esc}</v></c>'
        h_cell = f'<c r="H{row}" s="89" t="str"><f t="shared" si="1"/><v>{tester_esc}</v></c>'

    return (
        f'<row r="{row}" spans="1:26" ht="92.4" x14ac:dyDescent="0.3">'
        f"{a_cell}"
        f'<c r="B{row}" s="87" t="s"><v>{desc_idx}</v></c>'
        f'<c r="C{row}" s="87" t="s"><v>{pre_idx}</v></c>'
        f'<c r="D{row}" s="21" t="s"><v>{steps_idx}</v></c>'
        f'<c r="E{row}" s="88" t="s"><v>{expected_idx}</v></c>'
        f'<c r="F{row}" s="138" t="s"><v>{result_idx}</v></c>'
        f'<c r="G{row}" s="86"><v>{date_serial}</v></c>'
        f"{h_cell}"
        f'<c r="I{row}" s="90" t="s"><v>{note_idx}</v></c>'
        f'<c r="J{row}" s="91"/>'
        f'{pad_cols(row)}</row>'
    )


def build_sheet_xml(template_sheet: str, sections: list[dict], ss: SharedStrings, tester: str, pass_idx: int) -> str:
    header = extract_header_rows(template_sheet)
    tester_idx = ss.add(tester)
    header = patch_tester(header, tester, tester_idx)

    data_rows: list[str] = []
    row_num = 9
    section_num = 0
    case_serial = 0
    date_serial = 46167
    first_case_row: int | None = None

    for section in sections:
        section_num += 1
        title = f"{section_num}. Module {section['id']}: {section['title']}"
        title_idx = ss.add(title)
        data_rows.append(build_section_row(row_num, title_idx, date_serial, tester))
        row_num += 1
        date_serial += 1

        for case in section["cases"]:
            case_serial += 1
            if first_case_row is None:
                first_case_row = row_num
            data_rows.append(
                build_case_row(
                    row_num,
                    case_serial,
                    case,
                    date_serial,
                    tester,
                    ss,
                    pass_idx,
                    first_case_row,
                )
            )
            row_num += 1
            date_serial += 1

    last_row = row_num - 1
    header = patch_total_formula(header, last_row)

    prefix = re.split(r"<sheetData>", template_sheet, maxsplit=1)[0] + "<sheetData>"
    suffix = "</sheetData>" + re.split(r"</sheetData>", template_sheet, maxsplit=1)[1]

    sheet_data = "".join(header[r] for r in range(1, 9) if r in header) + "".join(data_rows)
    xml = prefix + sheet_data + suffix
    xml = re.sub(
        r'<dimension ref="[^"]*"',
        f'<dimension ref="A1:Z{max(last_row, 49)}"',
        xml,
        count=1,
    )
    return xml


def sanitize_sheet_name(section: dict) -> str:
    short_names = {
        "A": "Storefront",
        "B": "Giỏ & Coupon",
        "C": "Checkout",
        "D": "Auth khách",
        "E": "Đơn khách",
        "F": "Đánh giá SP",
        "G": "Blog",
        "H": "Admin Dashboard",
        "I": "Admin SP & Kho",
        "J": "Admin Đơn",
        "K": "Admin KH & Hoàn",
        "L": "Bảo mật",
        "M": "Giao diện",
        "N": "E2E Hoàn hàng",
    }
    sid = section["id"]
    label = short_names.get(sid, section["title"][:18])
    name = f"Module {sid} - {label}"
    name = re.sub(r"[\\/?*\[\]:]", "", name)
    return name[:31]


def update_workbook_xml(workbook_xml: str, sheet_defs: list[tuple[str, str, int]]) -> str:
    sheets_xml = []
    for name, rid, sheet_id in sheet_defs:
        esc = name.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
        sheets_xml.append(f'<sheet name="{esc}" sheetId="{sheet_id}" r:id="{rid}"/>')
    sheets_block = "<sheets>" + "".join(sheets_xml) + "</sheets>"
    workbook_xml = re.sub(r"<sheets>.*?</sheets>", sheets_block, workbook_xml, count=1, flags=re.S)
    workbook_xml = re.sub(r'activeTab="\d+"', 'activeTab="1"', workbook_xml)
    workbook_xml = re.sub(r'firstSheet="\d+"', 'firstSheet="0"', workbook_xml)
    return workbook_xml


def update_workbook_rels(rels_xml: str, sheet_count: int, max_rid: int) -> str:
    # Keep non-worksheet relationships, replace worksheet ones
    kept = []
    for m in re.finditer(r"<Relationship[^>]+/>", rels_xml):
        tag = m.group(0)
        if "worksheets/sheet" not in tag:
            kept.append(tag)
    rels = []
    for i in range(1, sheet_count + 1):
        rels.append(
            f'<Relationship Id="rId{i}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet{i}.xml"/>'
        )
    for tag in kept:
        m = re.search(r'Id="(rId\d+)"', tag)
        if m:
            rid_num = int(m.group(1).replace("rId", ""))
            if rid_num > sheet_count:
                kept_new_id = f"rId{sheet_count + len([t for t in kept if t == tag])}"
                # preserve other rels with incremented ids after sheets
    # simpler: rebuild from scratch with worksheets first then other rels from original
    other = []
    next_id = sheet_count + 1
    for m in re.finditer(r"<Relationship[^>]+/>", rels_xml):
        tag = m.group(0)
        if "worksheets/sheet" in tag:
            continue
        new_tag = re.sub(r'Id="rId\d+"', f'Id="rId{next_id}"', tag)
        # update targets that reference old rIds in workbook - only styles/sharedStrings/theme
        other.append((next_id, new_tag))
        next_id += 1

    parts = [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">',
    ]
    for i in range(1, sheet_count + 1):
        parts.append(
            f'<Relationship Id="rId{i}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet{i}.xml"/>'
        )
    for _, tag in sorted(other, key=lambda x: x[0]):
        parts.append(tag)
    parts.append("</Relationships>")
    return "".join(parts)


def update_content_types(ct_xml: str, sheet_count: int) -> str:
    ct_xml = re.sub(r'<Override[^>]+calcChain[^>]*/>', "", ct_xml)
    ct_xml = re.sub(r"<Override PartName=\"/xl/worksheets/sheet\d+\.xml\"[^>]*/>", "", ct_xml)
    insert_at = ct_xml.find("</Types>")
    overrides = []
    for i in range(1, sheet_count + 1):
        overrides.append(
            f'<Override PartName="/xl/worksheets/sheet{i}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        )
    return ct_xml[:insert_at] + "".join(overrides) + ct_xml[insert_at:]


def generate(output: Path) -> None:
    data = load_report_data()
    sections = data["sections"]
    meta = data["meta"]
    tester = meta.get("tester", "Nhóm phát triển đồ án")

    work = output.with_suffix(".tmp.xlsx")
    shutil.copy2(TEMPLATE_FILLED, work)

    with zipfile.ZipFile(work, "r") as zin:
        files = {name: zin.read(name) for name in zin.namelist()}

    template_sheet = files["xl/worksheets/sheet2.xml"].decode("utf-8")
    cover_sheet = files["xl/worksheets/sheet1.xml"]
    ss = SharedStrings(files["xl/sharedStrings.xml"].decode("utf-8"))
    pass_idx = ss.index.get("Pass", ss.add("Pass"))

    sheet_defs: list[tuple[str, str, int]] = [("Cover", "rId1", 1)]
    sheet_xmls: list[bytes] = [cover_sheet]

    # Tổng hợp (all modules)
    all_xml = build_sheet_xml(template_sheet, sections, ss, tester, pass_idx)
    sheet_defs.append(("Tổng hợp", "rId2", 2))
    sheet_xmls.append(all_xml.encode("utf-8"))

    # Per-module sheets
    sheet_id = 3
    for section in sections:
        name = sanitize_sheet_name(section)
        rid = f"rId{len(sheet_defs) + 1}"
        sheet_defs.append((name, rid, sheet_id))
        mod_xml = build_sheet_xml(template_sheet, [section], ss, tester, pass_idx)
        sheet_xmls.append(mod_xml.encode("utf-8"))
        sheet_id += 1

    sheet_count = len(sheet_defs)
    workbook_xml = update_workbook_xml(files["xl/workbook.xml"].decode("utf-8"), sheet_defs)
    rels_xml = update_workbook_rels(files["xl/_rels/workbook.xml.rels"].decode("utf-8"), sheet_count, sheet_count)
    ct_xml = update_content_types(files["[Content_Types].xml"].decode("utf-8"), sheet_count)
    shared_xml = ss.to_xml()

    # Remove old worksheet files and calcChain
    out_entries = {}
    for name, content in files.items():
        if name.startswith("xl/worksheets/sheet") and name.endswith(".xml"):
            continue
        if name == "xl/calcChain.xml":
            continue
        if name.startswith("xl/worksheets/_rels/"):
            continue
        out_entries[name] = content

    out_entries["xl/workbook.xml"] = workbook_xml.encode("utf-8")
    out_entries["xl/_rels/workbook.xml.rels"] = rels_xml.encode("utf-8")
    out_entries["[Content_Types].xml"] = ct_xml.encode("utf-8")
    out_entries["xl/sharedStrings.xml"] = shared_xml.encode("utf-8")
    for i, xml in enumerate(sheet_xmls, start=1):
        out_entries[f"xl/worksheets/sheet{i}.xml"] = xml

    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED) as zout:
        for name in files.keys():
            if name in out_entries:
                zout.writestr(name, out_entries[name])
        for name, content in out_entries.items():
            if name not in files:
                zout.writestr(name, content)

    work.unlink(missing_ok=True)

    total = sum(len(s["cases"]) for s in sections)
    summary = (
        f"Created: {output}\n"
        f"Sheets: {sheet_count} (Cover + Tong hop + {len(sections)} modules)\n"
        f"Test cases: {total}\n"
    )
    docs_copy = ROOT / "docs" / "Winsum-Test-Case-Template-Filled.xlsx"
    shutil.copy2(output, docs_copy)
    log_path = ROOT / "docs" / "export-log.txt"
    log_path.write_text(summary + f"Copy: {docs_copy}\n", encoding="utf-8")


def main() -> int:
    if not TEMPLATE_FILLED.is_file():
        print(f"Khong tim thay: {TEMPLATE_FILLED}", file=sys.stderr)
        return 1
    generate(OUTPUT)
    print(str(OUTPUT))
    print(str(ROOT / "docs" / "Winsum-Test-Case-Template-Filled.xlsx"))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
