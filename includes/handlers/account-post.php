<?php

declare(strict_types=1);

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

