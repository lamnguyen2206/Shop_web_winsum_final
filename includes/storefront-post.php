<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/flash.php';

function storefrontHandlePost(mysqli $conn, string $view): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if ($view === 'cart') {
        storefrontHandleCartPost($conn);
        return;
    }
    if ($view === 'catalog') {
        storefrontHandleCatalogPost($conn);
        return;
    }
    if ($view === 'product') {
        storefrontHandleProductPost($conn);
        return;
    }
    if ($view === 'checkout') {
        storefrontHandleCheckoutPost($conn);
        return;
    }
    if ($view === 'account') {
        storefrontHandleAccountPost($conn);
        return;
    }
    if ($view === 'order-detail') {
        storefrontHandleOrderDetailPost($conn);
        return;
    }
    if ($view === 'order-return') {
        storefrontHandleOrderReturnPost($conn);
        return;
    }
    if ($view === 'order-lookup') {
        storefrontHandleOrderLookupPost($conn);
        return;
    }
    if ($view === 'post') {
        storefrontHandleBlogPostPost($conn);
    }
}

function storefrontHandleBlogPostPost(mysqli $conn): void
{
    if (($_POST['action'] ?? '') !== 'submit_comment') {
        return;
    }

    require_once __DIR__ . '/blog-repository.php';
    require_once __DIR__ . '/blog-comment-repository.php';
    require_once __DIR__ . '/customer-auth.php';
    require_once __DIR__ . '/csrf.php';

    $slug = trim((string) ($_GET['slug'] ?? $_POST['post_slug'] ?? ''));
    $post = $slug !== '' ? blogGetPostBySlug($conn, $slug) : null;
    if (!$post) {
        return;
    }

    $notice = '';
    $success = false;

    if (!csrfValidate()) {
        $notice = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $currentCustomer = customerCurrent($conn);
        $result = blogCommentCreate(
            $conn,
            (int) $post['id'],
            (string) ($_POST['author_name'] ?? ($currentCustomer['full_name'] ?? '')),
            (string) ($_POST['author_email'] ?? ($currentCustomer['email'] ?? '')),
            (string) ($_POST['comment_content'] ?? ''),
            $currentCustomer ? (int) $currentCustomer['id'] : null
        );
        $notice = $result['message'];
        $success = $result['ok'];
    }

    if ($notice !== '') {
        pageFlashSet('post', $notice, $success);
    }
    redirect(app_url('post', ['slug' => $slug]) . '#post-comments');
}

function storefrontHandleOrderDetailPost(mysqli $conn): void
{
    $action = (string) ($_POST['action'] ?? '');
    if ($action !== 'cancel_order') {
        return;
    }

    require_once __DIR__ . '/order-repository.php';
    require_once __DIR__ . '/customer-auth.php';
    require_once __DIR__ . '/csrf.php';

    $code = trim((string) ($_POST['order_code'] ?? ''));
    $customer = customerCurrent($conn);
    if (!$customer || !csrfValidate()) {
        redirect(app_url('orders'));
    }

    $result = orderCustomerCancelForAccount($conn, (int) $customer['id'], $code);
    redirect(app_url('order-detail', ['code' => $code, 'msg' => $result['message'], 'ok' => $result['ok'] ? '1' : '0']));
}

function storefrontHandleOrderReturnPost(mysqli $conn): void
{
    if (($_POST['action'] ?? '') !== 'submit_return_request') {
        return;
    }

    require_once __DIR__ . '/order-repository.php';
    require_once __DIR__ . '/return-repository.php';
    require_once __DIR__ . '/customer-auth.php';
    require_once __DIR__ . '/csrf.php';

    $code = trim((string) ($_POST['order_code'] ?? ''));
    $customer = customerCurrent($conn);
    if (!$customer || !csrfValidate()) {
        redirect(app_url('orders'));
    }

    $order = orderGetCustomerOrderDetailByCode($conn, (int) $customer['id'], $code);
    if (!$order) {
        redirect(app_url('orders'));
    }

    $result = returnCreateRequest(
        $conn,
        (int) $order['id'],
        (int) $customer['id'],
        (string) ($_POST['return_reason'] ?? ''),
        (string) ($_POST['return_description'] ?? ''),
        [
            'bank_account_number' => (string) ($_POST['bank_account_number'] ?? ''),
            'bank_account_name' => (string) ($_POST['bank_account_name'] ?? ''),
            'bank_name' => (string) ($_POST['bank_name'] ?? ''),
        ]
    );

    if ($result['ok']) {
        redirect(app_url('order-detail', ['code' => $code, 'msg' => $result['message'], 'ok' => '1']));
    }

    redirect(app_url('order-return', ['code' => $code, 'msg' => $result['message'], 'ok' => '0']));
}

function storefrontHandleOrderLookupPost(mysqli $conn): void
{
    if (($_POST['action'] ?? '') !== 'cancel_guest_order') {
        return;
    }

    require_once __DIR__ . '/order-repository.php';
    require_once __DIR__ . '/csrf.php';
    require_once __DIR__ . '/customer-auth.php';

    if (customerCurrent($conn)) {
        redirect(app_url('orders'));
    }

    $code = trim((string) ($_POST['order_code'] ?? ''));
    $phone = trim((string) ($_POST['customer_phone'] ?? ''));
    if (!csrfValidate()) {
        redirect(app_url('order-lookup', [
            'code' => $code,
            'phone' => $phone,
            'msg' => 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.',
            'ok' => '0',
        ]));
    }
    if (!phoneIsValidVietnamMobile($phone)) {
        redirect(app_url('order-lookup', [
            'code' => $code,
            'phone' => $phone,
            'msg' => 'Số điện thoại phải có đúng 10 số và bắt đầu bằng số 0.',
            'ok' => '0',
        ]));
    }

    $phone = phoneNormalize($phone);
    $result = orderCustomerCancelByCodeAndPhone($conn, $code, $phone);
    redirect(app_url('order-lookup', [
        'code' => $code,
        'phone' => $phone,
        'msg' => $result['message'],
        'ok' => $result['ok'] ? '1' : '0',
    ]));
}

function storefrontHandleCartPost(mysqli $conn): void
{
    require_once __DIR__ . '/cart-store.php';
    require_once __DIR__ . '/coupon-repository.php';
    require_once __DIR__ . '/customer-auth.php';

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

function storefrontHandleCatalogPost(mysqli $conn): void
{
    if (($_POST['action'] ?? '') !== 'add_to_cart') {
        return;
    }

    require_once __DIR__ . '/product-repository.php';
    require_once __DIR__ . '/cart-store.php';
    require_once __DIR__ . '/customer-auth.php';

    $notice = '';
    $success = false;
    $params = array_diff_key($_GET, ['view' => true]);

    if (!csrfValidate()) {
        $notice = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $catalogCustomer = customerCurrent($conn);
        if (!customerMayShopOnStorefront($catalogCustomer)) {
            $notice = 'Tài khoản quản trị không thể thêm sản phẩm vào giỏ hàng.';
        } else {
            $productId = (int) ($_POST['product_id'] ?? 0);
            $qty = max(1, (int) ($_POST['qty'] ?? 1));
            $product = productGetById($conn, $productId);
            if (!$product) {
                $notice = 'Không tìm thấy sản phẩm.';
            } else {
                $cartQty = 0;
                foreach (cartGetItems() as $cartLine) {
                    if ((int) ($cartLine['product_id'] ?? 0) === (int) $product['id']) {
                        $cartQty += (int) ($cartLine['qty'] ?? 0);
                    }
                }
                $stockCheck = inventoryValidatePurchase($conn, (int) $product['id'], $product['stock_status'], $cartQty + $qty);
                if ($stockCheck['ok']) {
                    cartAddItem([
                        'id' => 'product-' . $product['id'],
                        'product_id' => $product['id'],
                        'slug' => $product['slug'],
                        'name' => $product['name'],
                        'sku' => $product['sku'],
                        'price' => $product['price'],
                        'image' => $product['image'],
                    ], $qty);
                    $notice = 'Đã thêm sản phẩm vào giỏ hàng.';
                    $success = true;
                } else {
                    $notice = $stockCheck['message'];
                }
            }
        }
    }

    pageFlashSet('catalog', $notice, $success);
    redirect(app_url('catalog', $params));
}

function storefrontHandleProductPost(mysqli $conn): void
{
    require_once __DIR__ . '/product-repository.php';
    require_once __DIR__ . '/cart-store.php';
    require_once __DIR__ . '/review-repository.php';
    require_once __DIR__ . '/customer-auth.php';

    $slug = trim((string) ($_GET['slug'] ?? ''));
    $product = $slug !== '' ? productGetBySlug($conn, $slug) : null;
    if (!$product) {
        return;
    }

    $action = (string) ($_POST['action'] ?? '');
    $notice = '';
    $success = false;

    if (!csrfValidate()) {
        $notice = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } elseif ($action === 'add_to_cart') {
        if (adminCurrent()) {
            $notice = 'Tài khoản quản trị không thể mua qua website.';
        } else {
            $qty = max(1, (int) ($_POST['qty'] ?? 1));
            $cartQty = 0;
            foreach (cartGetItems() as $cartLine) {
                if ((int) ($cartLine['product_id'] ?? 0) === (int) $product['id']) {
                    $cartQty += (int) ($cartLine['qty'] ?? 0);
                }
            }
            $stockCheck = inventoryValidatePurchase($conn, (int) $product['id'], $product['stock_status'], $cartQty + $qty);
            if (!$stockCheck['ok']) {
                $notice = $stockCheck['message'];
            } else {
                cartAddItem([
                    'id' => 'product-' . $product['id'],
                    'product_id' => $product['id'],
                    'slug' => $product['slug'],
                    'name' => $product['name'],
                    'sku' => $product['sku'],
                    'price' => (int) round($product['base_price']),
                    'image' => $product['images'][0]['url'] ?? 'assets/images/blog_1.png',
                ], $qty);
                $notice = 'Đã thêm sản phẩm vào giỏ hàng.';
                $success = true;
            }
        }
    } elseif ($action === 'submit_review') {
        $currentCustomer = customerCurrent($conn);
        $result = reviewCreate(
            $conn,
            (int) $product['id'],
            (string) ($_POST['reviewer_name'] ?? ($currentCustomer['full_name'] ?? '')),
            (string) ($_POST['reviewer_email'] ?? ($currentCustomer['email'] ?? '')),
            (int) ($_POST['rating'] ?? 5),
            (string) ($_POST['review_title'] ?? ''),
            (string) ($_POST['review_content'] ?? ''),
            $currentCustomer ? (int) $currentCustomer['id'] : null
        );
        $notice = $result['message'];
        $success = $result['ok'];
    }

    if ($notice !== '') {
        pageFlashSet('product', $notice, $success);
    }
    redirect(app_url('product', ['slug' => $slug]));
}

function storefrontHandleCheckoutPost(mysqli $conn): void
{
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'confirm_vietqr_demo') {
        storefrontHandleVietQrDemoConfirm($conn);
        return;
    }

    if (!isset($_POST['checkout_submit'])) {
        return;
    }

    require_once __DIR__ . '/cart-store.php';
    require_once __DIR__ . '/order-repository.php';
    require_once __DIR__ . '/customer-auth.php';

    $result = [
        'placed' => false,
        'message' => '',
        'code' => '',
    ];

    if (adminCurrent()) {
        $result['message'] = 'Tài khoản quản trị không thể đặt hàng qua website.';
    } elseif (!csrfValidate()) {
        $result['message'] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } else {
        $shippingMethods = orderGetShippingMethods($conn);
        $paymentMethods = orderGetPaymentMethods($conn);
        $currentCustomer = customerCurrent($conn);
        $customerId = $currentCustomer ? (int) $currentCustomer['id'] : null;

        cartSyncPricesFromDb($conn);
        $cartItems = cartGetItems();
        $totals = cartCalculateTotals($cartItems, $conn, $customerId);

        if ($cartItems === []) {
            $result['message'] = 'Giỏ hàng đang trống, chưa thể thanh toán.';
        } elseif ($shippingMethods === [] || $paymentMethods === []) {
            $result['message'] = 'Hệ thống chưa cấu hình phương thức vận chuyển hoặc thanh toán.';
        } else {
            $customerName = trim((string) ($_POST['customer_name'] ?? ''));
            $customerPhone = trim((string) ($_POST['customer_phone'] ?? ''));
            $customerEmail = trim((string) ($_POST['customer_email'] ?? ''));
            $customerProvince = trim((string) ($_POST['customer_province'] ?? ''));
            $customerWard = trim((string) ($_POST['customer_ward'] ?? ''));
            $customerStreet = trim((string) ($_POST['customer_street'] ?? ''));
            $customerAddress = storefrontBuildCheckoutAddress(
                $customerProvince,
                $customerWard,
                $customerStreet,
                (string) ($_POST['customer_address'] ?? '')
            );
            $customerNote = trim((string) ($_POST['customer_note'] ?? ''));
            $shippingMethodId = (int) ($_POST['shipping_method_id'] ?? 0);
            $paymentMethodId = (int) ($_POST['payment_method_id'] ?? 0);

            $selectedShipping = orderFindShippingMethod($shippingMethods, $shippingMethodId);
            $selectedPayment = orderFindPaymentMethod($paymentMethods, $paymentMethodId);

            if ($selectedShipping) {
                orderApplyShippingToSession($shippingMethods, $shippingMethodId);
            }
            if ($selectedPayment) {
                $_SESSION['checkout_payment_method_id'] = $paymentMethodId;
            }

            cartSyncPricesFromDb($conn);
            $cartItems = cartGetItems();
            $totals = cartCalculateTotals($cartItems, $conn, $customerId);

            if ($customerName === '' || $customerPhone === '' || $customerProvince === '' || $customerWard === '' || $customerStreet === '') {
                $result['message'] = 'Vui lòng điền đầy đủ thông tin nhận hàng.';
            } elseif (!phoneIsValidVietnamMobile($customerPhone)) {
                $result['message'] = 'Số điện thoại phải có đúng 10 số và bắt đầu bằng số 0.';
            } elseif (!$selectedShipping) {
                $result['message'] = 'Vui lòng chọn phương thức vận chuyển hợp lệ.';
            } elseif (!$selectedPayment) {
                $result['message'] = 'Vui lòng chọn phương thức thanh toán hợp lệ.';
            } else {
                $customerPhone = phoneNormalize($customerPhone);
                $cartCheck = cartValidateForCheckout($conn, $cartItems);
                if (!$cartCheck['ok']) {
                    $result['message'] = $cartCheck['message'];
                } elseif ($totals['coupon_code'] !== '') {
                    require_once __DIR__ . '/coupon-repository.php';
                    $couponCheck = couponValidate($conn, (string) $totals['coupon_code'], (float) $totals['subtotal'], $customerId, $customerPhone);
                    if (!$couponCheck['ok']) {
                        cartSetCoupon(null);
                        $result['message'] = $couponCheck['message'];
                    }
                } else {
                    $couponCheck = ['ok' => true];
                }

                if ($cartCheck['ok'] && ($couponCheck['ok'] ?? false)) {
                    try {
                        $couponCode = $totals['coupon_code'] !== '' ? $totals['coupon_code'] : null;
                        $couponId = $totals['coupon_id'] > 0 ? $totals['coupon_id'] : null;
                        $orderCode = orderCreateFromCheckout(
                            $conn,
                            [
                                'name' => $customerName,
                                'phone' => $customerPhone,
                                'email' => $customerEmail,
                                'address' => $customerAddress,
                                'note' => $customerNote,
                            ],
                            $cartItems,
                            $totals,
                            $couponCode,
                            $customerId,
                            $shippingMethodId,
                            $paymentMethodId,
                            $couponId
                        );
                        cartClear();
                        $_SESSION['last_order_code'] = $orderCode;
                        $_SESSION['last_order_phone'] = $customerPhone;
                        if ($couponId) {
                            require_once __DIR__ . '/coupon-repository.php';
                            couponRecordSessionOrderUseForPhone((int) $couponId, $customerPhone);
                        }
                        unset($_SESSION['checkout_shipping_method_id'], $_SESSION['checkout_payment_method_id']);
                        if ($shippingMethods !== []) {
                            $_SESSION['selected_shipping_fee'] = (int) $shippingMethods[0]['fee'];
                        }
                        $result['placed'] = true;
                        $result['code'] = $orderCode;
                        $result['total'] = (int) round((float) $totals['total']);
                        $result['payment_method_code'] = (string) ($selectedPayment['code'] ?? '');
                        $result['payment_method_name'] = (string) ($selectedPayment['name'] ?? '');
                        $result['message'] = 'Đặt hàng thành công. Mã đơn của bạn là ' . $orderCode . '.';
                    } catch (Throwable $e) {
                        $result['message'] = 'Không thể lưu đơn hàng vào hệ thống. Vui lòng thử lại.';
                    }
                }
            }
        }
    }

    $_SESSION['checkout_result'] = $result;
    redirect(app_url('checkout'));
}

function storefrontBuildCheckoutAddress(
    string $province,
    string $ward,
    string $street,
    string $legacyAddress = ''
): string {
    $parts = array_filter(
        [
            trim($street),
            trim($ward),
            trim($province),
        ],
        static fn(string $part): bool => $part !== ''
    );

    if ($parts === [] && trim($legacyAddress) !== '') {
        return trim($legacyAddress);
    }

    return implode(', ', $parts);
}

function storefrontHandleVietQrDemoConfirm(mysqli $conn): void
{
    require_once __DIR__ . '/order-repository.php';
    require_once __DIR__ . '/customer-auth.php';
    require_once __DIR__ . '/csrf.php';

    $orderCode = trim((string) ($_POST['order_code'] ?? ''));
    $sessionOrderCode = trim((string) ($_SESSION['last_order_code'] ?? ''));
    $sessionOrderPhone = trim((string) ($_SESSION['last_order_phone'] ?? ''));

    if (!csrfValidate()) {
        $_SESSION['checkout_result'] = [
            'placed' => true,
            'message' => 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.',
            'code' => $orderCode,
        ];
        redirect(app_url('checkout'));
    }

    $currentCustomer = customerCurrent($conn);
    $order = null;
    if ($currentCustomer) {
        $order = orderGetCustomerOrderDetailByCode($conn, (int) $currentCustomer['id'], $orderCode);
    } elseif ($orderCode !== '' && $orderCode === $sessionOrderCode && $sessionOrderPhone !== '') {
        $order = orderGetOrderDetailByCodeAndPhone($conn, $orderCode, $sessionOrderPhone);
    }

    if (!$order) {
        $_SESSION['checkout_result'] = [
            'placed' => true,
            'message' => 'Không tìm thấy đơn hàng để xác nhận thanh toán.',
            'code' => $orderCode,
        ];
        redirect(app_url('checkout'));
    }

    if (
        in_array((string) ($order['status'] ?? ''), ['cancelled', 'returned'], true)
        || in_array((string) ($order['fulfillment_status'] ?? ''), ['cancelled', 'returned'], true)
    ) {
        $_SESSION['checkout_result'] = [
            'placed' => true,
            'message' => 'Đơn hàng đã hủy hoặc hoàn trả nên không thể xác nhận thanh toán.',
            'code' => $orderCode,
            'total' => (int) round((float) ($order['grand_total'] ?? 0)),
        ];
        redirect(app_url('checkout'));
    }

    $paymentCode = (string) ($order['payment']['payment_method_code'] ?? '');
    if (!in_array($paymentCode, ['bank_transfer', 'vietqr'], true)) {
        $_SESSION['checkout_result'] = [
            'placed' => true,
            'message' => 'Đơn hàng này không sử dụng phương thức thanh toán VietQR.',
            'code' => $orderCode,
        ];
        redirect(app_url('checkout'));
    }

    if ((string) ($order['payment_status'] ?? '') !== 'paid') {
        $updated = orderUpdatePaymentStatus($conn, (int) $order['id'], 'paid', 'vietqr_demo');
        if (!$updated) {
            $_SESSION['checkout_result'] = [
                'placed' => true,
                'message' => 'Không thể cập nhật trạng thái thanh toán. Vui lòng thử lại.',
                'code' => $orderCode,
                'total' => (int) round((float) ($order['grand_total'] ?? 0)),
                'payment_method_code' => $paymentCode,
                'payment_method_name' => (string) ($order['payment']['payment_method_name'] ?? ''),
            ];
            redirect(app_url('checkout'));
        }
    }

    if ($currentCustomer) {
        redirect(app_url('orders', [
            'payment' => 'success',
            'code' => $orderCode,
        ]));
    }

    redirect(app_url('order-lookup', [
        'payment' => 'success',
        'code' => $orderCode,
        'phone' => $sessionOrderPhone,
    ]));
}

function storefrontHandleAccountPost(mysqli $conn): void
{
    $currentCustomer = customerCurrent($conn);
    if (!$currentCustomer) {
        return;
    }

    if (!csrfValidate()) {
        pageFlashSet('account', 'Phiên làm việc không hợp lệ.', false);
        redirect(app_url('account'));
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'logout') {
        customerLogout();
        $_SESSION['auth_flash'] = [
            'message' => 'Bạn đã đăng xuất.',
            'success' => true,
            'open' => null,
            'prefill' => [],
        ];
        redirect(app_url('home'));
    }

    if ($action === 'update_profile') {
        $result = customerUpdateProfile(
            $conn,
            (int) $currentCustomer['id'],
            (string) ($_POST['full_name'] ?? ''),
            (string) ($_POST['phone'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['new_password'] ?? '')
        );
        pageFlashSet('account', $result['message'], $result['ok']);
        redirect(app_url('account') . '#profile-edit');
    }
}
