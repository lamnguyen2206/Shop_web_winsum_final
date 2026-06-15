<?php

declare(strict_types=1);

/**
 * Xuất báo cáo kiểm thử HTML sang PDF (Chrome/Edge headless).
 * Chạy: C:\xampp\php\php.exe docs/generate-test-report-pdf.php
 */

$docsDir = __DIR__;
$htmlPath = $docsDir . DIRECTORY_SEPARATOR . 'bao-cao-kiem-thu.html';
$pdfPath = $docsDir . DIRECTORY_SEPARATOR . 'bao-cao-kiem-thu.pdf';

if (!is_file($htmlPath)) {
    fwrite(STDERR, "Chưa có bao-cao-kiem-thu.html. Chạy docs/generate-test-report.php trước.\n");
    exit(1);
}

function testReportFindBrowser(): ?string
{
    $candidates = [
        getenv('PROGRAMFILES') . DIRECTORY_SEPARATOR . 'Google' . DIRECTORY_SEPARATOR . 'Chrome' . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'chrome.exe',
        'C:' . DIRECTORY_SEPARATOR . 'Program Files' . DIRECTORY_SEPARATOR . 'Google' . DIRECTORY_SEPARATOR . 'Chrome' . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'chrome.exe',
        'C:' . DIRECTORY_SEPARATOR . 'Program Files (x86)' . DIRECTORY_SEPARATOR . 'Google' . DIRECTORY_SEPARATOR . 'Chrome' . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'chrome.exe',
        getenv('PROGRAMFILES') . DIRECTORY_SEPARATOR . 'Microsoft' . DIRECTORY_SEPARATOR . 'Edge' . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'msedge.exe',
        'C:' . DIRECTORY_SEPARATOR . 'Program Files' . DIRECTORY_SEPARATOR . 'Microsoft' . DIRECTORY_SEPARATOR . 'Edge' . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'msedge.exe',
        'C:' . DIRECTORY_SEPARATOR . 'Program Files (x86)' . DIRECTORY_SEPARATOR . 'Microsoft' . DIRECTORY_SEPARATOR . 'Edge' . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'msedge.exe',
    ];

    foreach ($candidates as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            return $path;
        }
    }

    return null;
}

$browser = testReportFindBrowser();
if ($browser === null) {
    fwrite(STDERR, "Không tìm thấy Chrome hoặc Edge.\n");
    fwrite(STDERR, "Cách thủ công: mở docs/bao-cao-kiem-thu.html → Ctrl+P → «Lưu dưới dạng PDF».\n");
    exit(1);
}

$htmlReal = realpath($htmlPath);
if ($htmlReal === false) {
    fwrite(STDERR, "Không đọc được đường dẫn HTML.\n");
    exit(1);
}

$htmlUrl = 'file:///' . str_replace('\\', '/', $htmlReal);
$pdfReal = realpath(dirname($pdfPath)) . DIRECTORY_SEPARATOR . basename($pdfPath);

$command = sprintf(
    '"%s" --headless=new --disable-gpu --no-pdf-header-footer --print-to-pdf="%s" "%s"',
    $browser,
    $pdfReal,
    $htmlUrl
);

passthru($command, $exitCode);

if ($exitCode !== 0 || !is_file($pdfReal)) {
    fwrite(STDERR, "Lỗi tạo PDF (mã thoát {$exitCode}).\n");
    fwrite(STDERR, "Thử mở docs/bao-cao-kiem-thu.html và in thủ công (Ctrl+P).\n");
    exit(1);
}

$sizeKb = round(filesize($pdfReal) / 1024, 1);
echo "Đã tạo PDF: {$pdfReal} ({$sizeKb} KB)\n";
