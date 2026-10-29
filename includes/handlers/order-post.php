<?php

declare(strict_types=1);

function storefrontHandleOrderDetailPost(mysqli $conn): void
{
    $action = (string) ($_POST['action'] ?? '');
    if (!in_array($action, ['cancel_order', 'confirm_refund_received'], true)) {
        return;
    }

    require_once __DIR__ . '/../order-repository.php';
    require_once __DIR__ . '/../customer-auth.php';
    require_once __DIR__ . '/../csrf.php';

    $code = trim((string) ($_POST['order_code'] ?? ''));
    $customer = customerCurrent($conn);
    if (!$customer || !csrfValidate()) {
        redirect(app_url('orders'));
    }

    if ($action === 'confirm_refund_received') {
        require_once __DIR__ . '/../return-repository.php';
        $order = orderGetCustomerOrderDetailByCode($conn, (int) $customer['id'], $code);
        if (!$order) {
            redirect(app_url('orders'));
        }
        $result = returnCustomerConfirmRefund($conn, (int) $order['id'], (int) $customer['id']);
        redirect(app_url('order-detail', ['code' => $code, 'msg' => $result['message'], 'ok' => $result['ok'] ? '1' : '0']));
    }

    $result = orderCustomerCancelForAccount($conn, (int) $customer['id'], $code);
    redirect(app_url('order-detail', ['code' => $code, 'msg' => $result['message'], 'ok' => $result['ok'] ? '1' : '0']));
}


function storefrontHandleOrderReturnPost(mysqli $conn): void
{
    if (($_POST['action'] ?? '') !== 'submit_return_request') {
        return;
    }

    require_once __DIR__ . '/../order-repository.php';
    require_once __DIR__ . '/../return-repository.php';
    require_once __DIR__ . '/../customer-auth.php';
    require_once __DIR__ . '/../csrf.php';

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
        ],
        [
            'ids' => is_array($_POST['return_item'] ?? null) ? $_POST['return_item'] : [],
            'qty' => is_array($_POST['return_qty'] ?? null) ? $_POST['return_qty'] : [],
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

    require_once __DIR__ . '/../order-repository.php';
    require_once __DIR__ . '/../csrf.php';
    require_once __DIR__ . '/../customer-auth.php';

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

