<?php

declare(strict_types=1);

/**
 * Điền 161 test case vào Template - Test Case.xlsx (sheet Import File), giữ format trường.
 * Chạy: C:\xampp\php\php.exe docs/generate-template-test-case.php
 */

$docsDir = __DIR__;
require_once $docsDir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'template-xlsx-filler.php';

$templatePath = $docsDir . DIRECTORY_SEPARATOR . 'Template - Test Case.xlsx';
$outputPath = $docsDir . DIRECTORY_SEPARATOR . 'Winsum-Test-Case-Template-Filled.xlsx';
$reportData = require $docsDir . DIRECTORY_SEPARATOR . 'test-report-data.php';

if (!is_file($templatePath)) {
    fwrite(STDERR, "Không tìm thấy template: {$templatePath}\n");
    exit(1);
}

$totalCases = 0;
foreach ($reportData['sections'] as $section) {
    $totalCases += count($section['cases']);
}

try {
    $filler = new TemplateXlsxFiller();
    $filler->fillImportFileSheet($templatePath, $outputPath, $reportData);
    $sizeKb = round(filesize($outputPath) / 1024, 1);
    echo "Đã tạo: {$outputPath} ({$sizeKb} KB)\n";
    echo "Sheet Import File: {$totalCases} test case, " . count($reportData['sections']) . " module\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Lỗi: ' . $e->getMessage() . "\n");
    exit(1);
}
