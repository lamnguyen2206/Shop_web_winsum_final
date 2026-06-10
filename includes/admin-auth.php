<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function customerIsAdminRole(?array $customer): bool
{
    return $customer !== null && ($customer['role'] ?? 'customer') === 'admin';
}

function adminSyncSessionForCustomer(?array $customer): void
{
    if (customerIsAdminRole($customer)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = (string) ($customer['full_name'] ?? 'Admin');
        $_SESSION['customer_role'] = 'admin';
        return;
    }

    unset($_SESSION['admin_logged_in'], $_SESSION['admin_username'], $_SESSION['customer_role']);
}

function adminCurrent(): bool
{
    return !empty($_SESSION['admin_logged_in']) || ($_SESSION['customer_role'] ?? '') === 'admin';
}

function adminLogout(): void
{
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_username'], $_SESSION['customer_role']);
}

/**
 * ID khách đang đăng nhập khi kiểm tra quyền trên panel admin.
 * Trả về 0 nếu là quản trị — cho phép xóa/khóa tài khoản khách khác (trừ role admin).
 */
function adminManagementActingCustomerId(): int
{
    if (adminCurrent()) {
        return 0;
    }

    return (int) ($_SESSION['customer_id'] ?? 0);
}

function adminRequire(): void
{
    if (adminCurrent()) {
        return;
    }

    $_SESSION['auth_flash'] = [
        'message' => 'Vui lòng đăng nhập tài khoản quản trị.',
        'success' => false,
        'open' => 'login',
        'prefill' => [],
    ];
    header('Location: index.php?view=home');
    exit;
}
