<?php

declare(strict_types=1);

/**
 * Sinh báo cáo kiểm thử HTML, Markdown, Excel và PDF cho đồ án Winsum Home.
 * Chạy: C:\xampp\php\php.exe docs/generate-test-report.php
 */

$docsDir = __DIR__;
$htmlPath = $docsDir . DIRECTORY_SEPARATOR . 'bao-cao-kiem-thu.html';
$mdPath = $docsDir . DIRECTORY_SEPARATOR . 'TEST-CASES.md';

$reportData = require $docsDir . DIRECTORY_SEPARATOR . 'test-report-data.php';
$meta = $reportData['meta'];
$sections = $reportData['sections'];

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

ob_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Báo cáo kiểm thử — <?= htmlspecialchars($meta['project']) ?></title>
<style>
@page { size: A4; margin: 18mm 15mm; }
* { box-sizing: border-box; }
body {
  font-family: "Segoe UI", "Times New Roman", serif;
  font-size: 11pt;
  line-height: 1.45;
  color: #1a1a1a;
  max-width: 210mm;
  margin: 0 auto;
  padding: 12mm;
}
.cover {
  text-align: center;
  page-break-after: always;
  padding-top: 35mm;
}
.cover h1 { font-size: 22pt; margin-bottom: 8px; color: #1e3a5f; }
.cover .sub { font-size: 13pt; color: #444; margin-bottom: 24px; }
.cover .meta { text-align: left; max-width: 420px; margin: 40px auto 0; font-size: 11pt; }
.cover .meta dt { font-weight: 600; float: left; width: 140px; clear: left; }
.cover .meta dd { margin: 0 0 8px 150px; }
h2 { font-size: 14pt; color: #1e3a5f; border-bottom: 2px solid #1e3a5f; padding-bottom: 4px; margin-top: 22px; page-break-after: avoid; }
h3 { font-size: 12pt; color: #333; margin-top: 16px; page-break-after: avoid; }
p, li { text-align: justify; }
table { width: 100%; border-collapse: collapse; margin: 10px 0 16px; font-size: 9pt; page-break-inside: auto; }
tr { page-break-inside: avoid; page-break-after: auto; }
th, td { border: 1px solid #bbb; padding: 5px 6px; vertical-align: top; }
th { background: #e8eef4; font-weight: 600; text-align: center; }
td.pass { color: #0a6640; font-weight: 600; text-align: center; }
td.fail { color: #a00; font-weight: 600; text-align: center; }
.summary-box {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  margin: 16px 0;
}
.summary-box div {
  border: 1px solid #ccc;
  padding: 12px;
  text-align: center;
  border-radius: 4px;
}
.summary-box strong { display: block; font-size: 18pt; color: #1e3a5f; }
.toc { page-break-after: always; }
.toc ol { line-height: 1.8; }
.note { background: #f5f8fc; border-left: 4px solid #1e3a5f; padding: 10px 14px; margin: 12px 0; font-size: 10pt; }
.section-block { page-break-before: auto; }
.footer-note { font-size: 9pt; color: #666; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 8px; }
@media print {
  body { padding: 0; }
  .no-print { display: none; }
}
</style>
</head>
<body>

<div class="cover">
  <h1>BÁO CÁO KIỂM THỬ HỆ THỐNG</h1>
  <p class="sub"><?= htmlspecialchars($meta['project']) ?></p>
  <p><?= htmlspecialchars($meta['subtitle']) ?></p>
  <dl class="meta">
    <dt>Phiên bản</dt><dd><?= htmlspecialchars($meta['version']) ?></dd>
    <dt>Ngày kiểm thử</dt><dd><?= htmlspecialchars($meta['date']) ?></dd>
    <dt>Người thực hiện</dt><dd><?= htmlspecialchars($meta['tester']) ?></dd>
    <dt>Phương pháp</dt><dd><?= htmlspecialchars($meta['method']) ?></dd>
    <dt>Tổng test case</dt><dd><?= $totalCases ?> case</dd>
  </dl>
</div>

<div class="toc">
  <h2>Mục lục</h2>
  <ol>
    <li>Mục tiêu kiểm thử</li>
    <li>Môi trường kiểm thử</li>
    <li>Phương pháp và phạm vi</li>
    <li>Tổng hợp kết quả</li>
    <?php $n = 5; foreach ($sections as $s): ?>
    <li>Module <?= htmlspecialchars($s['id']) ?>: <?= htmlspecialchars($s['title']) ?></li>
    <?php endforeach; ?>
    <li>Đánh giá, kết luận và hạn chế</li>
  </ol>
</div>

<h2>1. Mục tiêu kiểm thử</h2>
<p>Xác minh hệ thống Winsum Home đáp ứng các yêu cầu chức năng nghiệp vụ đồ án: duyệt và mua sản phẩm, quản lý giỏ hàng và mã giảm giá, đặt hàng (guest và thành viên), quy trình hoàn hàng 4 giai đoạn (yêu cầu → duyệt → nhận hàng → hoàn tiền), quản trị đơn hàng/sản phẩm/tồn kho, blog và duyệt nội dung, cùng các yêu cầu bảo mật cơ bản (CSRF, phân quyền, encoding UTF-8).</p>

<h2>2. Môi trường kiểm thử</h2>
<table>
  <tr><th>Thành phần</th><th>Cấu hình</th></tr>
  <?php foreach ($meta['environment'] as $k => $v): ?>
  <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars($v) ?></td></tr>
  <?php endforeach; ?>
</table>

<h2>3. Phương pháp và phạm vi</h2>
<ul>
  <li><strong>Kiểm thử chức năng (Functional):</strong> Theo từng use case storefront và admin.</li>
  <li><strong>Kiểm thử hộp đen:</strong> Không kiểm tra mã nguồn, chỉ đối chiếu đầu vào/đầu ra.</li>
  <li><strong>Kiểm thử bảo mật cơ bản:</strong> CSRF, SQL injection, XSS, phân quyền.</li>
  <li><strong>Phạm vi ngoài:</strong> Cổng thanh toán trực tuyến, email, kiểm thử tải, kiểm thử tự động PHPUnit.</li>
</ul>

<h2>4. Tổng hợp kết quả</h2>
<div class="summary-box">
  <div><strong><?= $totalCases ?></strong>Tổng TC</div>
  <div><strong><?= $passCount ?></strong>Pass</div>
  <div><strong><?= $failCount ?></strong>Fail</div>
  <div><strong><?= $passRate ?>%</strong>Tỷ lệ đạt</div>
</div>
<table>
  <tr><th>Module</th><th>Số TC</th><th>Pass</th><th>Fail</th></tr>
  <?php foreach ($sections as $section):
    $sTotal = count($section['cases']);
    $sPass = count(array_filter($section['cases'], fn($c) => ($c[4] ?? '') === 'Pass'));
    $sFail = $sTotal - $sPass;
  ?>
  <tr>
    <td><?= htmlspecialchars($section['id'] . ' — ' . $section['title']) ?></td>
    <td style="text-align:center"><?= $sTotal ?></td>
    <td style="text-align:center"><?= $sPass ?></td>
    <td style="text-align:center"><?= $sFail ?></td>
  </tr>
  <?php endforeach; ?>
  <tr style="font-weight:bold;background:#f0f0f0">
    <td>Tổng cộng</td>
    <td style="text-align:center"><?= $totalCases ?></td>
    <td style="text-align:center"><?= $passCount ?></td>
    <td style="text-align:center"><?= $failCount ?></td>
  </tr>
</table>

<?php foreach ($sections as $section): ?>
<div class="section-block">
  <h2>Module <?= htmlspecialchars($section['id']) ?>: <?= htmlspecialchars($section['title']) ?></h2>
  <table>
    <tr>
      <th style="width:8%">Mã TC</th>
      <th style="width:14%">Chức năng</th>
      <th style="width:22%">Các bước thực hiện</th>
      <th style="width:28%">Kết quả mong đợi</th>
      <th style="width:8%">Kết quả</th>
      <th style="width:8%">Ưu tiên</th>
    </tr>
    <?php foreach ($section['cases'] as $case):
      $result = $case[4] ?? '';
      $cls = $result === 'Pass' ? 'pass' : ($result === 'Fail' ? 'fail' : '');
    ?>
    <tr>
      <td><?= htmlspecialchars($case[0]) ?></td>
      <td><?= htmlspecialchars($case[1]) ?></td>
      <td><?= htmlspecialchars($case[2]) ?></td>
      <td><?= htmlspecialchars($case[3]) ?></td>
      <td class="<?= $cls ?>"><?= htmlspecialchars($result) ?></td>
      <td style="text-align:center"><?= htmlspecialchars($case[5] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endforeach; ?>

<h2>Đánh giá, kết luận và hạn chế</h2>
<h3>5.1. Đánh giá chung</h3>
<p>Hệ thống đã vượt qua <strong><?= $passCount ?>/<?= $totalCases ?></strong> test case (<?= $passRate ?>%), bao phủ đầy đủ luồng nghiệp vụ chính: mua hàng end-to-end, mã giảm giá, quản trị đơn/sản phẩm/tồn kho, blog và kiểm duyệt nội dung. Các cơ chế CSRF, prepared statement và phân quyền admin hoạt động đúng thiết kế.</p>

<h3>5.2. Kết luận</h3>
<p>Winsum Home <strong>đáp ứng yêu cầu chức năng</strong> của đồ án tốt nghiệp trong phạm vi kiểm thử đã thực hiện. Hệ thống sẵn sàng demo và bảo vệ đồ án.</p>

<h3>5.3. Hạn chế kiểm thử</h3>
<ul>
  <?php foreach ($meta['limitations'] as $item): ?>
  <li><?= htmlspecialchars($item) ?></li>
  <?php endforeach; ?>
</ul>

<h3>5.4. Hướng phát triển</h3>
<ul>
  <?php foreach ($meta['future'] as $item): ?>
  <li><?= htmlspecialchars($item) ?></li>
  <?php endforeach; ?>
</ul>

<p class="no-print" style="margin-top:20px;text-align:center">
  <button type="button" onclick="window.print()" style="padding:10px 20px;font-size:11pt;cursor:pointer">In / Lưu PDF (Ctrl+P)</button>
</p>

<div class="footer-note">
  Tài liệu được sinh tự động từ <code>docs/generate-test-report.php</code> — <?= htmlspecialchars($meta['date']) ?>.
  Chạy lại: <code>C:\xampp\php\php.exe docs/generate-test-report.php</code> |
  Excel: <code>docs/bao-cao-kiem-thu.xlsx</code> |
  PDF: <code>docs/bao-cao-kiem-thu.pdf</code>
</div>

</body>
</html>
<?php
$html = ob_get_clean();
file_put_contents($htmlPath, $html);
echo "Đã tạo HTML: {$htmlPath}\n";

$md = "# Báo cáo kiểm thử — {$meta['project']}\n\n";
$md .= "**Ngày:** {$meta['date']} | **Tổng TC:** {$totalCases} | **Pass:** {$passCount} | **Tỷ lệ:** {$passRate}%\n\n";
foreach ($sections as $section) {
    $md .= "## Module {$section['id']}: {$section['title']}\n\n";
    $md .= "| Mã TC | Chức năng | Các bước | Kết quả mong đợi | Kết quả | Ưu tiên |\n";
    $md .= "|-------|-----------|----------|------------------|---------|--------|\n";
    foreach ($section['cases'] as $case) {
        $md .= '| ' . implode(' | ', array_map(static fn($v) => str_replace('|', '\\|', (string) $v), $case)) . " |\n";
    }
    $md .= "\n";
}
file_put_contents($mdPath, $md);
echo "Đã tạo Markdown: {$mdPath}\n";

$excelScript = $docsDir . DIRECTORY_SEPARATOR . 'generate-test-report-excel.php';
if (is_file($excelScript)) {
    passthru('"' . PHP_BINARY . '" "' . $excelScript . '"', $excelCode);
} else {
    echo "Không tìm thấy {$excelScript}\n";
}

$pdfScript = $docsDir . DIRECTORY_SEPARATOR . 'generate-test-report-pdf.php';
if (is_file($pdfScript)) {
    passthru('"' . PHP_BINARY . '" "' . $pdfScript . '"', $pdfCode);
} else {
    echo "Không tìm thấy {$pdfScript}\n";
}

$templateScript = $docsDir . DIRECTORY_SEPARATOR . 'generate-template-test-case.php';
if (is_file($templateScript)) {
    passthru('"' . PHP_BINARY . '" "' . $templateScript . '"', $templateCode);
} else {
    echo "Không tìm thấy {$templateScript}\n";
}
