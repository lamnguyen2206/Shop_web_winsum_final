<?php

declare(strict_types=1);

/**
 * Sinh file test case Excel (mỗi module một sheet).
 * Ưu tiên script Python (không cần ext-zip). Fallback PHP nếu có ZipArchive.
 *
 * Chạy: C:\xampp\php\php.exe docs/testing/generate-template-test-case.php
 */

$rootDir = dirname(__DIR__, 2);
$docsDir = __DIR__;
$pythonScript = $rootDir . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'generate-filled-test-xlsx.py';
$outputPath = $rootDir . DIRECTORY_SEPARATOR . 'Winsum-Test-Case-Template-Filled (1).xlsx';

if (is_file($pythonScript)) {
    $cmd = 'python "' . $pythonScript . '"';
    passthru($cmd, $code);
    if ($code === 0 && is_file($outputPath)) {
        $sizeKb = round(filesize($outputPath) / 1024, 1);
        echo "Output: {$outputPath} ({$sizeKb} KB)\n";
        exit(0);
    }
    fwrite(STDERR, "Python script failed (code {$code}), thử PHP fallback...\n");
}

require_once $rootDir . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'template-xlsx-filler.php';

$templatePath = $rootDir . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'Template - Test Case.xlsx';
$reportData = require $docsDir . DIRECTORY_SEPARATOR . 'test-report-data.php';

if (!is_file($templatePath)) {
    fwrite(STDERR, "Không tìm thấy template: {$templatePath}\n");
    exit(1);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "Cần bật php_zip hoặc chạy: python scripts/generate-filled-test-xlsx.py\n");
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
