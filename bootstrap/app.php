<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', appDebug() ? '1' : '0');
ini_set('log_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/routes.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/customer-auth.php';
require_once __DIR__ . '/../includes/customer-auth-post.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/inventory-repository.php';
require_once __DIR__ . '/../includes/admin-post.php';
require_once __DIR__ . '/../includes/handlers/storefront-post.php';
require_once __DIR__ . '/../includes/blog-editor-handler.php';

customerBootstrapAdminAccount($conn);
require_once __DIR__ . '/../includes/order-repository.php';
require_once __DIR__ . '/../includes/review-repository.php';
require_once __DIR__ . '/../includes/return-repository.php';
require_once __DIR__ . '/../includes/blog-comment-repository.php';
require_once __DIR__ . '/../includes/blog-repository.php';
blogEnsureDefaults($conn);

$requestedView = isset($_GET['view'])
    ? (string) $_GET['view']
    : (isset($_POST['view']) ? (string) $_POST['view'] : 'home');
$requestedView = rtrim(trim($requestedView), '.');
if ($requestedView === '') {
    $requestedView = 'home';
}
$resolved = appResolveView($requestedView);
$view = $resolved['view'];

customerAuthHandlePost($conn);
adminHandlePost($conn, $view);
storefrontHandlePost($conn, $view);
blogEditorHandlePost($conn, $view);

if (str_starts_with($view, 'admin') || $view === 'blog-editor') {
    adminRequire();
}

if ($view === 'account' && adminCurrent() && !customerCurrent($conn)) {
    redirect(app_url('home'));
}

if ($requestedView === 'admin-login') {
    if (adminCurrent()) {
        redirect(app_url('admin-dashboard'));
    }
    $_SESSION['auth_flash'] = [
        'message' => 'Đăng nhập quản trị bằng SĐT hoặc email của tài khoản admin tại form Đăng nhập.',
        'success' => false,
        'open' => 'login',
        'prefill' => [],
    ];
    redirect(auth_login_url('home'));
}

$authFlash = customerAuthConsumeFlash();
$authMessage = $authFlash['message'];
$authSuccess = $authFlash['success'];
$authOpenModal = $authFlash['open'];
if ($authOpenModal === null && isset($_GET['auth']) && in_array($_GET['auth'], ['login', 'register'], true)) {
    $authOpenModal = (string) $_GET['auth'];
}
$authPrefill = $authFlash['prefill'];
if ($authPrefill === [] && isset($_GET['phone']) && trim((string) $_GET['phone']) !== '') {
    $authPrefill = ['phone' => trim((string) $_GET['phone'])];
}
$currentCustomer = customerCurrent($conn);
$isAdmin = adminCurrent();
$storefrontGuest = !$currentCustomer && !$isAdmin;

$pageTitle = $resolved['page_title'];
$includeFile = $resolved['include_file'];
$is404 = $resolved['is_404'];
$assets = appAssetsForView($view, $storefrontGuest);
$extraStyles = $assets['styles'];
$extraScripts = $assets['scripts'];
