<?php

declare(strict_types=1);

/**
 * Xử lý POST đăng nhập / đăng ký / đăng xuất, PRG về đúng trang hiện tại.
 */
function customerAuthRedirectAfterPost(): void
{
    $qs = $_GET;
    $target = 'index.php';
    if ($qs !== []) {
        $target .= '?' . http_build_query($qs);
    }
    header('Location: ' . $target, true, 303);
    exit;
}

/**
 * @return array{message:string,success:bool,open:?string,prefill:array<string,string>}
 */
function customerAuthConsumeFlash(): array
{
    if (empty($_SESSION['auth_flash']) || !is_array($_SESSION['auth_flash'])) {
        return [
            'message' => '',
            'success' => false,
            'open' => null,
            'prefill' => [],
        ];
    }
    $f = $_SESSION['auth_flash'];
    unset($_SESSION['auth_flash']);
    $prefill = $f['prefill'] ?? [];
    if (!is_array($prefill)) {
        $prefill = [];
    }
    $open = $f['open'] ?? null;
    return [
        'message' => (string) ($f['message'] ?? ''),
        'success' => (bool) ($f['success'] ?? false),
        'open' => is_string($open) && $open !== '' ? $open : null,
        'prefill' => array_map(static fn ($v) => (string) $v, $prefill),
    ];
}

function customerAuthHandlePost(mysqli $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['auth_action'])) {
        return;
    }

    require_once __DIR__ . '/csrf.php';
    require_once __DIR__ . '/customer-auth.php';

    $action = (string) $_POST['auth_action'];

    if (!csrfValidate()) {
        $_SESSION['auth_flash'] = [
            'message' => 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.',
            'success' => false,
            'open' => in_array($action, ['login', 'register'], true) ? $action : null,
            'prefill' => [],
        ];
        customerAuthRedirectAfterPost();
    }

    if ($action === 'login') {
        $result = customerLogin($conn, (string) ($_POST['identifier'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($result['ok'] && !empty($result['is_admin'])) {
            $_SESSION['auth_flash'] = [
                'message' => 'Đăng nhập thành công! Chào mừng admin quay trở lại.',
                'success' => true,
                'open' => null,
                'prefill' => [],
            ];
            header('Location: index.php?view=home', true, 303);
            exit;
        }
        $_SESSION['auth_flash'] = [
            'message' => $result['message'],
            'success' => $result['ok'],
            'open' => $result['ok'] ? null : 'login',
            'prefill' => $result['ok'] ? [] : ['identifier' => (string) ($_POST['identifier'] ?? '')],
        ];
        customerAuthRedirectAfterPost();
    }

    if ($action === 'register') {
        $result = customerRegister(
            $conn,
            (string) ($_POST['full_name'] ?? ''),
            (string) ($_POST['phone'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        $_SESSION['auth_flash'] = [
            'message' => $result['message'],
            'success' => $result['ok'],
            'open' => $result['ok'] ? null : 'register',
            'prefill' => $result['ok'] ? [] : [
                'phone' => (string) ($_POST['phone'] ?? ''),
                'full_name' => (string) ($_POST['full_name'] ?? ''),
                'email' => (string) ($_POST['email'] ?? ''),
            ],
        ];
        customerAuthRedirectAfterPost();
    }

    if ($action === 'logout') {
        require_once __DIR__ . '/admin-auth.php';
        customerLogout();
        $_SESSION['auth_flash'] = [
            'message' => 'Bạn đã đăng xuất.',
            'success' => true,
            'open' => null,
            'prefill' => [],
        ];
        customerAuthRedirectAfterPost();
    }

    $_SESSION['auth_flash'] = [
        'message' => 'Thao tác không hợp lệ.',
        'success' => false,
        'open' => null,
        'prefill' => [],
    ];
    customerAuthRedirectAfterPost();
}
