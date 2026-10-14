<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vietqr.php';

$vietQrOrderCode = trim((string) ($vietQrOrderCode ?? ''));
$vietQrAmount = (int) ($vietQrAmount ?? 0);
$vietQr = vietqrPayload($vietQrOrderCode, $vietQrAmount);
?>
<div class="vietqr-box">
    <h2>Thanh toán VietQR</h2>
    <?php if ($vietQr === null): ?>
        <p>Đơn đang chờ chuyển khoản, nhưng cửa hàng chưa cấu hình tài khoản nhận tiền trong <code>config/vietqr.php</code>.</p>
    <?php else: ?>
        <p>Quét mã bằng ứng dụng ngân hàng. Chuyển đúng số tiền và giữ nguyên nội dung là mã đơn. Đơn chuyển sang đã thanh toán sau khi shop đối soát tiền vào tài khoản.</p>
        <img src="<?php echo e($vietQr['image_url']); ?>" alt="Mã VietQR thanh toán đơn <?php echo e($vietQr['content']); ?>">
        <dl>
            <div>
                <dt>Ngân hàng</dt>
                <dd><?php echo e($vietQr['bank_code']); ?></dd>
            </div>
            <div>
                <dt>Số tài khoản</dt>
                <dd><?php echo e($vietQr['account_number']); ?></dd>
            </div>
            <div>
                <dt>Chủ tài khoản</dt>
                <dd><?php echo e($vietQr['account_name']); ?></dd>
            </div>
            <div>
                <dt>Số tiền</dt>
                <dd><?php echo number_format($vietQr['amount'], 0, ',', '.'); ?>đ</dd>
            </div>
            <div>
                <dt>Nội dung</dt>
                <dd><?php echo e($vietQr['content']); ?></dd>
            </div>
        </dl>
        <small>Không tự ghi đã thanh toán. Sai số tiền hoặc sai nội dung thì đơn vẫn ở trạng thái chưa thanh toán.</small>
    <?php endif; ?>
</div>
