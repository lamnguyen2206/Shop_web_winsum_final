<?php

declare(strict_types=1);

/**
 * Sinh báo cáo kiểm thử Excel (.xlsx) có định dạng cho đồ án Winsum Home.
 * Chạy: C:\xampp\php\php.exe docs/generate-test-report-excel.php
 */

$docsDir = __DIR__;
require_once dirname($docsDir) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'styled-xlsx-writer.php';

$data = require $docsDir . DIRECTORY_SEPARATOR . 'test-report-data.php';
$meta = $data['meta'];
$sections = $data['sections'];
$outputPath = $docsDir . DIRECTORY_SEPARATOR . 'bao-cao-kiem-thu.xlsx';

$totalCases = 0;
$passCount = 0;
$failCount = 0;
foreach ($sections as $section) {
    foreach ($section['cases'] as $case) {
        $totalCases++;
        if (($case[4] ?? '') === 'Pass') {
            $passCount++;
        } elseif (($case[4] ?? '') === 'Fail') {
            $failCount++;
        }
    }
}
$passRate = $totalCases > 0 ? round(($passCount / $totalCases) * 100, 1) : 0;

$writer = new StyledXlsxWriter();

// --- Sheet 1: Tổng quan (bìa) ---
$cover = [];
$blank = static fn() => ['value' => '', 'style' => StyledXlsxWriter::STYLE_DEFAULT];
$cell = static fn($v, int $style) => ['value' => $v, 'style' => $style];

$cover[] = [$blank(), $blank(), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell('BÁO CÁO KIỂM THỬ HỆ THỐNG', StyledXlsxWriter::STYLE_TITLE), $blank(), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell($meta['project'], StyledXlsxWriter::STYLE_SUBTITLE), $blank(), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell($meta['subtitle'], StyledXlsxWriter::STYLE_BODY), $blank(), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$blank(), $blank(), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell('Thông tin chung', StyledXlsxWriter::STYLE_LABEL), $blank(), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell('Phiên bản', StyledXlsxWriter::STYLE_LABEL), $cell($meta['version'], StyledXlsxWriter::STYLE_BODY), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell('Ngày kiểm thử', StyledXlsxWriter::STYLE_LABEL), $cell($meta['date'], StyledXlsxWriter::STYLE_BODY), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell('Người thực hiện', StyledXlsxWriter::STYLE_LABEL), $cell($meta['tester'], StyledXlsxWriter::STYLE_BODY), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell('Phương pháp', StyledXlsxWriter::STYLE_LABEL), $cell($meta['method'], StyledXlsxWriter::STYLE_BODY), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$blank(), $blank(), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell('Tổng hợp kết quả', StyledXlsxWriter::STYLE_LABEL), $blank(), $blank(), $blank(), $blank(), $blank()];
$cover[] = [
    $cell('Tổng test case', StyledXlsxWriter::STYLE_HEADER),
    $cell('Pass', StyledXlsxWriter::STYLE_HEADER),
    $cell('Fail', StyledXlsxWriter::STYLE_HEADER),
    $cell('Tỷ lệ đạt (%)', StyledXlsxWriter::STYLE_HEADER),
    $blank(),
    $blank(),
];
$cover[] = [
    $cell($totalCases, StyledXlsxWriter::STYLE_BODY),
    $cell($passCount, StyledXlsxWriter::STYLE_PASS),
    $cell($failCount, StyledXlsxWriter::STYLE_FAIL),
    $cell($passRate, StyledXlsxWriter::STYLE_BODY),
    $blank(),
    $blank(),
];
$cover[] = [$blank(), $blank(), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell('Môi trường kiểm thử', StyledXlsxWriter::STYLE_LABEL), $blank(), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell('Thành phần', StyledXlsxWriter::STYLE_HEADER), $cell('Cấu hình', StyledXlsxWriter::STYLE_HEADER), $blank(), $blank(), $blank(), $blank()];
foreach ($meta['environment'] as $k => $v) {
    $cover[] = [$cell($k, StyledXlsxWriter::STYLE_LABEL), $cell($v, StyledXlsxWriter::STYLE_BODY), $blank(), $blank(), $blank(), $blank()];
}
$cover[] = [$blank(), $blank(), $blank(), $blank(), $blank(), $blank()];
$cover[] = [$cell('Kết luận', StyledXlsxWriter::STYLE_LABEL), $blank(), $blank(), $blank(), $blank(), $blank()];
$conclusionRow = count($cover) + 1;
$cover[] = [$cell($meta['conclusion'], StyledXlsxWriter::STYLE_BODY), $blank(), $blank(), $blank(), $blank(), $blank()];

$writer->addRawSheet(
    'Tổng quan',
    $cover,
    ['A2:F2', 'A3:F3', 'A4:F4', 'A' . $conclusionRow . ':F' . $conclusionRow],
    0,
    [22, 28, 12, 12, 12, 12]
);

// --- Sheet 2: Tổng hợp module ---
$moduleRows = [['STT', 'Module', 'Tên module', 'Số TC', 'Pass', 'Fail', 'Tỷ lệ (%)']];
$stt = 1;
foreach ($sections as $section) {
    $sTotal = count($section['cases']);
    $sPass = count(array_filter($section['cases'], static fn($c) => ($c[4] ?? '') === 'Pass'));
    $sFail = $sTotal - $sPass;
    $rate = $sTotal > 0 ? round(($sPass / $sTotal) * 100, 1) : 0;
    $moduleRows[] = [$stt++, $section['id'], $section['title'], $sTotal, $sPass, $sFail, $rate];
}
$moduleRows[] = ['', 'Tổng', '', $totalCases, $passCount, $failCount, $passRate];
$moduleLastRow = count($moduleRows) - 1;

$writer->addSheet(
    'Tổng hợp module',
    $moduleRows,
    [],
    1,
    [6, 10, 42, 10, 10, 10, 12],
    'A1:G' . count($moduleRows),
    static function ($value, int $r, int $c) use ($moduleLastRow): int {
        if ($r === 0) {
            return StyledXlsxWriter::STYLE_HEADER;
        }
        if ($r === $moduleLastRow) {
            return StyledXlsxWriter::STYLE_TOTAL;
        }
        if ($c === 4) {
            return StyledXlsxWriter::STYLE_PASS;
        }
        if ($c === 5 && (int) $value > 0) {
            return StyledXlsxWriter::STYLE_FAIL;
        }
        return StyledXlsxWriter::STYLE_BODY;
    }
);

// --- Sheet 3: Chi tiết test case ---
$detailHeader = ['STT', 'Mã TC', 'Module', 'Tên module', 'Chức năng', 'Các bước thực hiện', 'Kết quả mong đợi', 'Kết quả', 'Ưu tiên', 'Người test', 'Ngày test', 'Ghi chú'];
$detailRows = [$detailHeader];
$seq = 1;
foreach ($sections as $section) {
    foreach ($section['cases'] as $case) {
        $detailRows[] = [
            $seq++,
            $case[0],
            $section['id'],
            $section['title'],
            $case[1],
            $case[2],
            $case[3],
            $case[4],
            $case[5] ?? '',
            '',
            '',
            '',
        ];
    }
}

$writer->addSheet(
    'Chi tiết test case',
    $detailRows,
    [],
    1,
    [5, 11, 8, 28, 22, 36, 38, 10, 12, 14, 12, 20],
    'A1:L' . count($detailRows),
    static function ($value, int $r, int $c): int {
        if ($r === 0) {
            return StyledXlsxWriter::STYLE_HEADER;
        }
        if ($c === 7) {
            if ($value === 'Pass') {
                return StyledXlsxWriter::STYLE_PASS;
            }
            if ($value === 'Fail') {
                return StyledXlsxWriter::STYLE_FAIL;
            }
        }
        return StyledXlsxWriter::STYLE_BODY;
    }
);

// --- Sheet: Hạn chế & hướng phát triển ---
$notes = [
    ['Nội dung', 'Chi tiết'],
    ['Hạn chế kiểm thử', ''],
];
foreach ($meta['limitations'] as $item) {
    $notes[] = ['', '• ' . $item];
}
$notes[] = ['', ''];
$notes[] = ['Hướng phát triển', ''];
foreach ($meta['future'] as $item) {
    $notes[] = ['', '• ' . $item];
}

$writer->addSheet(
    'Hạn chế & phát triển',
    $notes,
    [],
    1,
    [24, 70],
    null,
    static function ($value, int $r, int $c): int {
        if ($r === 0) {
            return StyledXlsxWriter::STYLE_HEADER;
        }
        if ($c === 0 && $value !== '') {
            return StyledXlsxWriter::STYLE_LABEL;
        }
        return StyledXlsxWriter::STYLE_BODY;
    }
);

// --- Sheet từng module ---
foreach ($sections as $section) {
    $rows = [['Mã TC', 'Chức năng', 'Các bước', 'Kết quả mong đợi', 'Kết quả', 'Ưu tiên']];
    foreach ($section['cases'] as $case) {
        $rows[] = [$case[0], $case[1], $case[2], $case[3], $case[4], $case[5] ?? ''];
    }
    $writer->addSheet(
        'Module ' . $section['id'],
        $rows,
        [],
        1,
        [11, 24, 36, 40, 10, 12],
        'A1:F' . count($rows),
        static function ($value, int $r, int $c): int {
            if ($r === 0) {
                return StyledXlsxWriter::STYLE_HEADER;
            }
            if ($c === 4) {
                if ($value === 'Pass') {
                    return StyledXlsxWriter::STYLE_PASS;
                }
                if ($value === 'Fail') {
                    return StyledXlsxWriter::STYLE_FAIL;
                }
            }
            return StyledXlsxWriter::STYLE_BODY;
        }
    );
}

try {
    $writer->save($outputPath);
    $sizeKb = round(filesize($outputPath) / 1024, 1);
    echo "Đã tạo Excel: {$outputPath} ({$sizeKb} KB)\n";
    echo "Tổng: {$totalCases} TC | Pass: {$passCount} | Fail: {$failCount} | Tỷ lệ: {$passRate}%\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Lỗi: ' . $e->getMessage() . "\n");
    exit(1);
}
