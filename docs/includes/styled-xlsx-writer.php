<?php

declare(strict_types=1);

/**
 * Ghi file .xlsx có style (màu, border, merge, freeze, độ rộng cột).
 */
final class StyledXlsxWriter
{
    public const STYLE_DEFAULT = 0;
    public const STYLE_TITLE = 1;
    public const STYLE_SUBTITLE = 2;
    public const STYLE_LABEL = 3;
    public const STYLE_HEADER = 4;
    public const STYLE_BODY = 5;
    public const STYLE_PASS = 6;
    public const STYLE_FAIL = 7;
    public const STYLE_TOTAL = 8;

    /** @var list<array{
     *   name: string,
     *   rows: list<list<array{value: scalar|null, style: int}>>,
     *   merges: list<string>,
     *   freezeRow: int,
     *   colWidths: list<float>,
     *   autoFilter: string|null
     * }> */
    private array $sheets = [];

    /**
     * @param list<list<array{value: scalar|null, style: int}>> $styledRows
     * @param list<string> $merges
     * @param list<float> $colWidths
     */
    public function addRawSheet(
        string $name,
        array $styledRows,
        array $merges = [],
        int $freezeRow = 0,
        array $colWidths = [],
        ?string $autoFilter = null
    ): void {
        $safeName = mb_substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/u', '', $name) ?: 'Sheet', 0, 31);
        if ($safeName === '') {
            $safeName = 'Sheet';
        }
        $this->sheets[] = [
            'name' => $safeName,
            'rows' => $styledRows,
            'merges' => $merges,
            'freezeRow' => $freezeRow,
            'colWidths' => $colWidths,
            'autoFilter' => $autoFilter,
        ];
    }

    /**
     * @param list<list<scalar|null>> $rows
     * @param list<string> $merges
     * @param list<float> $colWidths
     */
    public function addSheet(
        string $name,
        array $rows,
        array $merges = [],
        int $freezeRow = 0,
        array $colWidths = [],
        ?string $autoFilter = null,
        ?callable $rowStyleResolver = null
    ): void {
        $safeName = mb_substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/u', '', $name) ?: 'Sheet', 0, 31);
        if ($safeName === '') {
            $safeName = 'Sheet';
        }

        $styledRows = [];
        foreach ($rows as $r => $row) {
            $styledRow = [];
            foreach ($row as $c => $value) {
                $style = self::STYLE_BODY;
                if ($rowStyleResolver !== null) {
                    $style = (int) $rowStyleResolver($value, $r, $c, $row);
                }
                $styledRow[] = ['value' => $value, 'style' => $style];
            }
            $styledRows[] = $styledRow;
        }

        $this->sheets[] = [
            'name' => $safeName,
            'rows' => $styledRows,
            'merges' => $merges,
            'freezeRow' => $freezeRow,
            'colWidths' => $colWidths,
            'autoFilter' => $autoFilter,
        ];
    }

    public function save(string $path): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Cần bật extension zip trong php.ini để xuất .xlsx.');
        }

        $shared = [];
        $sharedIndex = [];
        $getSharedIndex = static function (string $value) use (&$shared, &$sharedIndex): int {
            if (!isset($sharedIndex[$value])) {
                $sharedIndex[$value] = count($shared);
                $shared[] = $value;
            }
            return $sharedIndex[$value];
        };

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Không tạo được file: {$path}");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStringsXml($shared));

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($i + 1) . '.xml',
                $this->sheetXml($sheet, $getSharedIndex)
            );
        }

        $zip->close();
    }

    private function contentTypesXml(): string
    {
        $overrides = '';
        $count = count($this->sheets);
        for ($i = 1; $i <= $count; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . $overrides . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $id = $i + 1;
            $name = htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $sheets .= '<sheet name="' . $name . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets></workbook>';
    }

    private function workbookRelsXml(): string
    {
        $rels = '';
        foreach ($this->sheets as $i => $sheet) {
            $id = $i + 1;
            $rels .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
        }
        $styleId = count($this->sheets) + 1;
        $rels .= '<Relationship Id="rId' . $styleId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $stringsId = $styleId + 1;
        $rels .= '<Relationship Id="rId' . $stringsId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0%"/></numFmts>'
            . '<fonts count="5">'
            . '<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="18"/><color rgb="FF1E3A5F"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="12"/><color rgb="FF1E3A5F"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="7">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A5F"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD4EDDA"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF8D7DA"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8EEF4"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF5F8FC"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFB0B0B0"/></left>'
            . '<right style="thin"><color rgb="FFB0B0B0"/></right>'
            . '<top style="thin"><color rgb="FFB0B0B0"/></top>'
            . '<bottom style="thin"><color rgb="FFB0B0B0"/></bottom><diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="9">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    /** @param list<string> $shared */
    private function sharedStringsXml(array $shared): string
    {
        $items = '';
        foreach ($shared as $text) {
            $items .= '<si><t xml:space="preserve">' . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($shared) . '" uniqueCount="' . count($shared) . '">'
            . $items . '</sst>';
    }

    /**
     * @param array{name: string, rows: list<list<array{value: scalar|null, style: int}>>, merges: list<string>, freezeRow: int, colWidths: list<float>, autoFilter: string|null} $sheet
     * @param callable(string): int $getSharedIndex
     */
    private function sheetXml(array $sheet, callable $getSharedIndex): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        if ($sheet['freezeRow'] > 0) {
            $topLeft = 'A' . ($sheet['freezeRow'] + 1);
            $xml .= '<sheetViews><sheetView workbookViewId="0">'
                . '<pane ySplit="' . $sheet['freezeRow'] . '" topLeftCell="' . $topLeft . '" activePane="bottomLeft" state="frozen"/>'
                . '</sheetView></sheetViews>';
        }

        if ($sheet['colWidths'] !== []) {
            $xml .= '<cols>';
            foreach ($sheet['colWidths'] as $i => $width) {
                $col = $i + 1;
                $xml .= '<col min="' . $col . '" max="' . $col . '" width="' . $width . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($sheet['rows'] as $r => $row) {
            $rowNum = $r + 1;
            $cells = '';
            foreach ($row as $c => $cell) {
                $col = $this->columnLetter((int) $c);
                $ref = $col . $rowNum;
                $value = $cell['value'];
                $style = ' s="' . (int) $cell['style'] . '"';
                if ($value === null || $value === '') {
                    $cells .= '<c r="' . $ref . '"' . $style . '/>';
                    continue;
                }
                if (is_int($value) || is_float($value)) {
                    $cells .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
                    continue;
                }
                $idx = $getSharedIndex((string) $value);
                $cells .= '<c r="' . $ref . '" t="s"' . $style . '><v>' . $idx . '</v></c>';
            }
            $xml .= '<row r="' . $rowNum . '">' . $cells . '</row>';
        }
        $xml .= '</sheetData>';

        if ($sheet['merges'] !== []) {
            $xml .= '<mergeCells count="' . count($sheet['merges']) . '">';
            foreach ($sheet['merges'] as $merge) {
                $xml .= '<mergeCell ref="' . $merge . '"/>';
            }
            $xml .= '</mergeCells>';
        }

        if ($sheet['autoFilter'] !== null && $sheet['autoFilter'] !== '') {
            $xml .= '<autoFilter ref="' . htmlspecialchars($sheet['autoFilter'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"/>';
        }

        $xml .= '</worksheet>';
        return $xml;
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $n = $index;
        do {
            $letter = chr(65 + ($n % 26)) . $letter;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letter;
    }
}
