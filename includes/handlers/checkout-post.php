<?php

declare(strict_types=1);

function storefrontHandleCheckoutPost(mysqli $conn): void
{
    if (!isset($_POST['checkout_submit'])) {
        return;
    }

    require_once __DIR__ . '/../cart-store.php';
    require_once __DIR__ . '/../order-repository.php';
    require_once __DIR__ . '/../customer-auth.php';

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
                    require_once __DIR__ . '/../coupon-repository.php';
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
                            require_once __DIR__ . '/../coupon-repository.php';
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
                        try {
                            require_once __DIR__ . '/../mailer.php';
                            mailSendOrderConfirmation($conn, $orderCode);
                        } catch (Throwable $mailError) {
                            // Đặt hàng vẫn thành công khi không gửi được email.
                        }
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

