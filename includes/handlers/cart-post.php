<?php

declare(strict_types=1);

function storefrontHandleCartPost(mysqli $conn): void
{
    require_once __DIR__ . '/../cart-store.php';
    require_once __DIR__ . '/../coupon-repository.php';
    require_once __DIR__ . '/../customer-auth.php';

    $notice = '';
    $success = false;

    if (adminCurrent()) {
        $notice = 'Tài khoản quản trị không thể thao tác giỏ hàng mua hàng.';
    } elseif (!csrfValidate()) {
        $notice = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $currentCustomer = customerCurrent($conn);
        $customerId = $currentCustomer ? (int) $currentCustomer['id'] : null;

        if ($action === 'update_qty' && isset($_POST['qty']) && is_array($_POST['qty'])) {
            $qtyMap = $_POST['qty'];
            $previewItems = cartGetItems();
            foreach ($previewItems as &$line) {
                $lineId = (string) $line['id'];
                if (isset($qtyMap[$lineId])) {
                    $line['qty'] = max(1, (int) $qtyMap[$lineId]);
                }
            }
            unset($line);
            $invCheck = inventoryValidateCartItems($conn, $previewItems);
            if (!$invCheck['ok']) {
                $notice = $invCheck['message'];
            } else {
                cartUpdateQuantities($qtyMap);
                $notice = 'Đã cập nhật số lượng sản phẩm.';
                $success = true;
            }
        } elseif ($action === 'remove_item' && isset($_POST['item_id'])) {
            cartRemoveItemById((string) $_POST['item_id']);
            $notice = 'Đã xóa sản phẩm khỏi giỏ hàng.';
            $success = true;
        } elseif ($action === 'apply_coupon') {
            $coupon = strtoupper(trim((string) ($_POST['coupon_code'] ?? '')));
            $previousCode = couponGetAppliedCode();
            if ($coupon === '') {
                cartSetCoupon(null);
                $_SESSION['cart_coupon_auto_skip'] = true;
                $notice = 'Đã xóa mã giảm giá.';
                $success = true;
            } else {
                cartSyncPricesFromDb($conn);
                $items = cartGetItems();
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += ((int) $item['price']) * ((int) $item['qty']);
                }
                $validation = couponValidate($conn, $coupon, (float) $subtotal, $customerId);
                if ($validation['ok']) {
                    cartSetCoupon($validation['coupon']);
                    if ($previousCode === $coupon) {
                        $notice = 'Mã giảm giá này đang được áp dụng.';
                    } elseif ($previousCode !== '') {
                        $notice = 'Đã thay mã giảm giá. Mỗi đơn chỉ được áp dụng một mã.';
                    } else {
                        $notice = $validation['message'];
                    }
                    $success = true;
                    if (!$customerId) {
                        couponMarkSessionCartApply((int) $validation['coupon']['id'], couponGuestLimitKey());
                    }
                } else {
                    cartSetCoupon(null);
                    $notice = $validation['message'];
                    $success = false;
                }
            }
        }
    }

    pageFlashSet('cart', $notice, $success);
    redirect(app_url('cart'));
}

