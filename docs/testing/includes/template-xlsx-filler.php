<?php

declare(strict_types=1);

/**
 * Điền test case vào sheet Import File của Template - Test Case.xlsx (giữ format trường).
 */
final class TemplateXlsxFiller
{
    private const PROJECT_ITEM_NAME = 'Shop web Winsum Testing';

    /** @var list<string> */
    private array $sharedSiBlocks = [];

    private int $passStringIndex = 0;

    public function fillImportFileSheet(string $templatePath, string $outputPath, array $reportData): void
    {
        if (!copy($templatePath, $outputPath)) {
            throw new RuntimeException("Không copy được template: {$templatePath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($outputPath) !== true) {
            throw new RuntimeException("Không mở được file: {$outputPath}");
        }

        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml === false) {
            throw new RuntimeException('Không đọc được sharedStrings.xml');
        }
        $this->loadSharedStrings($sharedXml);
        $this->passStringIndex = $this->findSharedIndex('Pass');

        $sheetXml = $zip->getFromName('xl/worksheets/sheet4.xml');
        if ($sheetXml === false) {
            throw new RuntimeException('Không đọc được sheet4.xml');
        }

        $newSheetXml = $this->buildSheet4($sheetXml, $reportData);
        $zip->addFromString('xl/worksheets/sheet4.xml', $newSheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $this->buildSharedStringsXml());

        // calcChain cũ tham chiếu dòng 49 — xóa để Excel tự tính lại
        $zip->deleteName('xl/calcChain.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($rels !== false) {
            $rels = preg_replace('/<Relationship[^>]+calcChain[^>]+\/>/i', '', $rels) ?? $rels;
            $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        }
        $contentTypes = $zip->getFromName('[Content_Types].xml');
        if ($contentTypes !== false) {
            $contentTypes = preg_replace('/<Override[^>]+calcChain[^>]+\/>/i', '', $contentTypes) ?? $contentTypes;
            $zip->addFromString('[Content_Types].xml', $contentTypes);
        }

        $zip->close();
    }

    private function loadSharedStrings(string $xml): void
    {
        preg_match_all('/<si>.*?<\/si>/s', $xml, $matches);
        $this->sharedSiBlocks = $matches[0] ?: [];
    }

    private function findSharedIndex(string $text): int
    {
        foreach ($this->sharedSiBlocks as $i => $block) {
            if (preg_match('/<t[^>]*>(.*?)<\/t>/s', $block, $m)) {
                $val = html_entity_decode($m[1], ENT_XML1, 'UTF-8');
                if ($val === $text) {
                    return $i;
                }
            }
        }
        return $this->addSharedString($text);
    }

    private function addSharedString(string $text): int
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? $text;
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $this->sharedSiBlocks[] = '<si><t xml:space="preserve">' . $escaped . '</t></si>';
        return count($this->sharedSiBlocks) - 1;
    }

    private function buildSharedStringsXml(): string
    {
        $count = count($this->sharedSiBlocks);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">'
            . implode('', $this->sharedSiBlocks)
            . '</sst>';
    }

    private function buildSheet4(string $templateXml, array $reportData): string
    {
        $meta = $reportData['meta'];
        $sections = $reportData['sections'];
        $tester = (string) ($meta['tester'] ?? 'Nhóm phát triển đồ án');

        // Giữ header rows 1-8 từ template
        if (!preg_match('/^(.*?<sheetData>)(.*?)(<\/sheetData>.*)$/s', $templateXml, $parts)) {
            throw new RuntimeException('Không parse được sheetData.');
        }
        $prefix = $parts[1];
        $suffix = $parts[3];
        preg_match_all('/<row r="(\d+)"[^>]*>.*?<\/row>/s', $parts[2], $rowMatches, PREG_SET_ORDER);
        $headerRows = [];
        foreach ($rowMatches as $rm) {
            if ((int) $rm[1] <= 8) {
                $headerRows[(int) $rm[1]] = $rm[0];
            }
        }

        // Cập nhật tester ở B3 (dùng cho công thức H=B3)
        if (isset($headerRows[3])) {
            $testerIdx = $this->addSharedString($tester);
            $headerRows[3] = preg_replace(
                '/<c r="B3"[^>]*>.*?<\/c>/',
                '<c r="B3" s="151" t="s"><v>' . $testerIdx . '</v></c>',
                $headerRows[3]
            ) ?? $headerRows[3];
        }

        $dataRows = [];
        $rowNum = 9;
        $sectionNum = 0;
        $caseSerial = 0;
        $dateSerial = 46167;

        foreach ($sections as $section) {
            $sectionNum++;
            $sectionTitle = $sectionNum . '. Module ' . $section['id'] . ': ' . $section['title'];
            $sectionTitleIdx = $this->addSharedString($sectionTitle);
            $dataRows[] = $this->buildSectionRow($rowNum, $sectionTitleIdx, $dateSerial++, $tester);
            $rowNum++;

            foreach ($section['cases'] as $case) {
                $caseSerial++;
                $dataRows[] = $this->buildCaseRow(
                    $rowNum,
                    $caseSerial,
                    $case,
                    $dateSerial++,
                    $tester
                );
                $rowNum++;
            }
        }

        $lastRow = $rowNum - 1;

        // Patch row 5 E5 formula range
        if (isset($headerRows[5])) {
            $headerRows[5] = preg_replace(
                '/COUNTA\(A9:A\d+\)/',
                'COUNTA(A9:A' . $lastRow . ')',
                $headerRows[5]
            ) ?? $headerRows[5];
            // Xóa giá trị cache cũ (33 TC) để Excel tự tính lại khi mở file
            $headerRows[5] = preg_replace('/<f>([^<]+)<\/f><v>[^<]*<\/v>/', '<f>$1</f>', $headerRows[5]) ?? $headerRows[5];
        }

        $sheetData = '';
        for ($r = 1; $r <= 8; $r++) {
            if (isset($headerRows[$r])) {
                $sheetData .= $headerRows[$r];
            }
        }
        $sheetData .= implode('', $dataRows);

        $xml = $prefix . $sheetData . $suffix;

        // dimension
        $xml = preg_replace(
            '/<dimension ref="[^"]*"/',
            '<dimension ref="A1:Z' . max($lastRow, 49) . '"',
            $xml,
            1
        ) ?? $xml;

        return $xml;
    }

    private function buildSectionRow(int $row, int $titleIdx, int $dateSerial, string $tester): string
    {
        return '<row r="' . $row . '" spans="1:26" ht="14.25" customHeight="1" x14ac:dyDescent="0.3">'
            . '<c r="A' . $row . '" s="81"/>'
            . '<c r="B' . $row . '" s="81" t="s"><v>' . $titleIdx . '</v></c>'
            . '<c r="C' . $row . '" s="82"/>'
            . '<c r="D' . $row . '" s="82"/>'
            . '<c r="E' . $row . '" s="82"/>'
            . '<c r="F' . $row . '" s="82"/>'
            . '<c r="G' . $row . '" s="86"><v>' . $dateSerial . '</v></c>'
            . '<c r="H' . $row . '" s="89" t="str"><f>B3</f><v>' . htmlspecialchars($tester, ENT_XML1) . '</v></c>'
            . '<c r="I' . $row . '" s="84"/>'
            . '<c r="J' . $row . '" s="85"/>'
            . $this->padCols($row, 'K', 'Z', '56')
            . '</row>';
    }

    /**
     * @param array{0:string,1:string,2:string,3:string,4:string,5?:string} $case
     */
    private function buildCaseRow(int $row, int $caseSerial, array $case, int $dateSerial, string $tester): string
    {
        $code = (string) $case[0];
        $desc = (string) $case[1];
        $steps = (string) $case[2];
        $expected = (string) $case[3];
        $result = (string) ($case[4] ?? 'Pass');
        $priority = (string) ($case[5] ?? '');

        $preCondition = $this->derivePreCondition($code, $priority);
        $note = $code;

        $descIdx = $this->addSharedString($desc);
        $preIdx = $this->addSharedString($preCondition);
        $stepsIdx = $this->addSharedString($steps);
        $expectedIdx = $this->addSharedString($expected);
        $noteIdx = $this->addSharedString($note);

        $resultIdx = $result === 'Pass' ? $this->passStringIndex : $this->addSharedString($result);

        $idFormula = 'IF(AND(E' . $row . '=""),"","["&amp;TEXT($B$1,"##")&amp;"-"&amp;TEXT(ROW()-9-COUNTBLANK($E$8:E'
            . ($row - 1) . ')+1,"##")&amp;"]")';
        $idValue = '[' . self::PROJECT_ITEM_NAME . '-' . $caseSerial . ']';

        if ($row === 10) {
            $aCell = '<c r="A' . $row . '" s="86" t="str"><f t="shared" ref="A10:A500" si="0">'
                . $idFormula . '</f><v>' . htmlspecialchars($idValue, ENT_XML1) . '</v></c>';
            $hCell = '<c r="H' . $row . '" s="89" t="str"><f t="shared" ref="H10:H500" si="1">$B$3</f><v>'
                . htmlspecialchars($tester, ENT_XML1) . '</v></c>';
        } else {
            $aCell = '<c r="A' . $row . '" s="89" t="str"><f t="shared" si="0"/><v>'
                . htmlspecialchars($idValue, ENT_XML1) . '</v></c>';
            $hCell = '<c r="H' . $row . '" s="89" t="str"><f t="shared" si="1"/><v>'
                . htmlspecialchars($tester, ENT_XML1) . '</v></c>';
        }

        return '<row r="' . $row . '" spans="1:26" ht="92.4" x14ac:dyDescent="0.3">'
            . $aCell
            . '<c r="B' . $row . '" s="87" t="s"><v>' . $descIdx . '</v></c>'
            . '<c r="C' . $row . '" s="87" t="s"><v>' . $preIdx . '</v></c>'
            . '<c r="D' . $row . '" s="21" t="s"><v>' . $stepsIdx . '</v></c>'
            . '<c r="E' . $row . '" s="88" t="s"><v>' . $expectedIdx . '</v></c>'
            . '<c r="F' . $row . '" s="138" t="s"><v>' . $resultIdx . '</v></c>'
            . '<c r="G' . $row . '" s="86"><v>' . $dateSerial . '</v></c>'
            . $hCell
            . '<c r="I' . $row . '" s="90" t="s"><v>' . $noteIdx . '</v></c>'
            . '<c r="J' . $row . '" s="91"/>'
            . $this->padCols($row, 'K', 'Z', '91')
            . '</row>';
    }

    private function padCols(int $row, string $from, string $to, string $style): string
    {
        $out = '';
        foreach (range($from, $to) as $col) {
            $out .= '<c r="' . $col . $row . '" s="' . $style . '"/>';
        }
        return $out;
    }

    private function derivePreCondition(string $code, string $priority): string
    {
        if (str_starts_with($code, 'TC-D') || str_starts_with($code, 'TC-E')) {
            return 'Đã có tài khoản khách hàng và/hoặc đơn hàng mẫu trong hệ thống';
        }
        if (str_starts_with($code, 'TC-H') || str_starts_with($code, 'TC-I') || str_starts_with($code, 'TC-J') || str_starts_with($code, 'TC-K')) {
            return 'Có tài khoản admin; đăng nhập admin@winsumhome.vn thành công';
        }
        if (str_starts_with($code, 'TC-N')) {
            return 'Đơn hàng đã giao (delivered) trong vòng 7 ngày; dữ liệu mẫu sẵn sàng';
        }
        if (str_starts_with($code, 'TC-C') || str_starts_with($code, 'TC-B')) {
            return 'Có sản phẩm còn hàng trong giỏ hoặc catalog';
        }
        return 'Hệ thống Winsum Home đang chạy tại http://localhost/webwinsum';
    }
}
