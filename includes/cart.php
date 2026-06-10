<?php
require_once __DIR__ . '/cart-store.php';
require_once __DIR__ . '/coupon-repository.php';

$cartFlash = pageFlashConsume('cart');
$cartNotice = $cartFlash['message'];
$cartBlockedAdmin = adminCurrent();
$currentCustomer = customerCurrent($conn);
$customerId = $currentCustomer ? (int) $currentCustomer['id'] : null;

cartSyncPricesFromDb($conn);
$cartItems = cartGetItems();
$stockLabels = [
    'in_stock' => 'Còn hàng',
    'out_of_stock' => 'Hết hàng',
    'preorder' => 'Đặt trước',
];
$totals = cartCalculateTotals($cartItems, $conn, $customerId);

$couponAutoNotice = '';
if ($cartItems !== [] && !$cartBlockedAdmin && $cartNotice === '') {
    $couponAutoNotice = couponTryAutoApplyBestDeal(
        $conn,
        (float) $totals['subtotal'],
        (float) $totals['shipping'],
        $customerId
    ) ?? '';
    if ($couponAutoNotice !== '') {
        $totals = cartCalculateTotals($cartItems, $conn, $customerId);
    }
}

$couponAppliedCode = couponGetAppliedCode();
$couponSuggestions = $cartItems !== [] && !$cartBlockedAdmin
    ? couponGetSuggestions($conn, (float) $totals['subtotal'], (float) $totals['shipping'], $customerId)
    : [];
?>

<section class="container cart-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Giỏ hàng</span></p>
    <h1>Giỏ hàng của bạn</h1>

    <?php if ($cartNotice !== ''): ?>
        <p class="cart-notice"><?php echo htmlspecialchars($cartNotice); ?></p>
    <?php elseif ($couponAutoNotice !== ''): ?>
        <p class="cart-notice cart-notice--success"><?php echo htmlspecialchars($couponAutoNotice); ?></p>
    <?php endif; ?>

    <div class="cart-layout">
        <div>
            <?php if (empty($cartItems)): ?>
                <div class="cart-empty">
                    <p>Giỏ hàng của bạn đang trống.</p>
                    <a class="read-more" href="<?php echo e(app_url('catalog')); ?>">Tiếp tục mua sắm</a>
                </div>
            <?php else: ?>
                <form method="post" action="index.php?view=cart">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="update_qty">
                    <div class="cart-table">
                        <div class="cart-table-header">
                            <span>Sản phẩm</span>
                            <span>Đơn giá</span>
                            <span>Số lượng</span>
                            <span>Thành tiền</span>
                        </div>

                        <?php foreach ($cartItems as $index => $item): ?>
                            <article class="cart-row">
                                <div class="cart-product">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <div>
                                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <p>Mã sản phẩm: <?php echo htmlspecialchars($item['sku']); ?></p>
                                        <?php
                                        $itemStock = (string) ($item['stock_status'] ?? 'in_stock');
                                        if ($itemStock === 'preorder'): ?>
                                            <span class="cart-preorder-badge">Đặt trước (Nhận hàng sau 15 ngày)</span>
                                        <?php elseif (isset($stockLabels[$itemStock])): ?>
                                            <span class="cart-stock-badge cart-stock-badge--<?php echo htmlspecialchars($itemStock); ?>"><?php echo htmlspecialchars($stockLabels[$itemStock]); ?></span>
                                        <?php endif; ?>
                                        <button class="remove-item-btn" type="submit" form="remove-<?php echo htmlspecialchars($item['id']); ?>">Xóa</button>
                                    </div>
                                </div>

                                <div class="cart-price">
                                    <?php echo number_format($item['price'], 0, ',', '.'); ?>đ
                                </div>

                                <div class="qty-control">
                                    <input type="number" min="1" value="<?php echo $item['qty']; ?>" name="qty[<?php echo htmlspecialchars($item['id']); ?>]" aria-label="Số lượng sản phẩm <?php echo $index + 1; ?>">
                                </div>

                                <div class="cart-total">
                                    <?php echo number_format($item['price'] * $item['qty'], 0, ',', '.'); ?>đ
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <button class="update-cart-btn" type="submit">CẬP NHẬT GIỎ HÀNG</button>
                </form>

                <?php foreach ($cartItems as $item): ?>
                    <form id="remove-<?php echo htmlspecialchars($item['id']); ?>" method="post" action="index.php?view=cart">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['id']); ?>">
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <aside class="cart-summary">
            <h2>Tóm tắt đơn hàng</h2>
            <div class="summary-line">
                <span>Tạm tính</span>
                <strong><?php echo number_format($totals['subtotal'], 0, ',', '.'); ?>đ</strong>
            </div>
            <div class="summary-line">
                <span>Phí vận chuyển</span>
                <strong><?php echo number_format($totals['shipping'], 0, ',', '.'); ?>đ</strong>
            </div>
            <div class="summary-line">
                <span>Giảm giá</span>
                <strong><?php echo number_format($totals['discount'], 0, ',', '.'); ?>đ</strong>
            </div>
            <div class="summary-line total">
                <span>Tổng cộng</span>
                <strong><?php echo number_format($totals['total'], 0, ',', '.'); ?>đ</strong>
            </div>

            <div class="coupon-form">
                <p class="coupon-form__label">Mã giảm giá</p>
                <?php if ($couponAppliedCode !== ''): ?>
                    <p class="coupon-form__applied">
                        <span class="coupon-form__applied-badge">Đang dùng: <?php echo e($couponAppliedCode); ?></span>
                    </p>
                <?php endif; ?>
                <form method="post" action="index.php?view=cart" class="coupon-form__manual">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="apply_coupon">
                    <div class="coupon-form__row">
                        <input class="coupon-form__input" type="text" name="coupon_code" placeholder="Nhập mã" value="<?php echo htmlspecialchars($_SESSION['cart_coupon'] ?? ''); ?>" autocomplete="off">
                        <button type="submit" class="coupon-form__btn">Áp dụng</button>
                    </div>
                </form>
                <?php if (!empty($couponSuggestions)): ?>
                    <button type="button" class="coupon-form__picker" data-coupon-modal-open aria-haspopup="dialog">
                        Chọn mã giảm giá
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($cartBlockedAdmin): ?>
                <p class="cart-help">Tài khoản quản trị không thanh toán qua website.</p>
            <?php elseif (empty($cartItems)): ?>
                <p class="cart-help">Thêm sản phẩm vào giỏ để thanh toán.</p>
            <?php else: ?>
                <a class="checkout-btn-link" href="<?php echo e(app_url('checkout')); ?>">TIẾN HÀNH THANH TOÁN</a>
            <?php endif; ?>
            <p class="cart-help">Hotline hỗ trợ đặt hàng nhanh: <a href="tel:0387239676">0387 239 676</a></p>
        </aside>
    </div>
</section>

<?php if (!empty($couponSuggestions)): ?>
    <?php require __DIR__ . '/coupon-suggestions.php'; ?>
<?php endif; ?>