<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function customerGenerateCode(): string
{
    return 'WS' . date('Ymd') . random_int(1000, 9999);
}

/**
 * Tự thêm cột role và tài khoản admin mặc định nếu DB chưa có (sau khi cập nhật code).
 */
function customerBootstrapAdminAccount(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $colCheck = $conn->query("SHOW COLUMNS FROM customers LIKE 'role'");
    if ($colCheck && $colCheck->num_rows === 0) {
        return;
    }

    $stmt = $conn->prepare("SELECT id FROM customers WHERE role = 'admin' LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($exists) {
        return;
    }

    $config = function_exists('appConfig') ? appConfig() : [];
    $admin = is_array($config['admin'] ?? null) ? $config['admin'] : [];
    $code = 'ADM00001';
    $name = (string) ($admin['name'] ?? 'admin');
    $phone = (string) ($admin['phone'] ?? '0901000000');
    $email = (string) ($admin['email'] ?? 'admin@winsumhome.vn');
    $password = (string) ($admin['password'] ?? '');
    if ($password === '') {
        return;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $status = 'active';
    $role = 'admin';

    $insert = $conn->prepare(
        "INSERT INTO customers (customer_code, full_name, phone, email, password_hash, status, role)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$insert) {
        return;
    }
    $insert->bind_param('sssssss', $code, $name, $phone, $email, $hash, $status, $role);
    @$insert->execute();
    $insert->close();
}

function customerGetById(mysqli $conn, int $customerId): ?array
{
    $stmt = $conn->prepare("SELECT id, customer_code, full_name, phone, email, status, role
                            FROM customers
                            WHERE id = ?
                            LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function customerCurrent(mysqli $conn): ?array
{
    $customerId = isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : 0;
    if ($customerId <= 0) {
        return null;
    }
    $customer = customerGetById($conn, $customerId);
    if ($customer === null || ($customer['status'] ?? '') !== 'active') {
        unset($_SESSION['customer_id']);
        return null;
    }

    require_once __DIR__ . '/admin-auth.php';
    adminSyncSessionForCustomer($customer);

    return $customer;
}

function customerLoginRateLimitWindowSeconds(): int
{
    return 900;
}

function customerLoginRateLimitMaxAttempts(): int
{
    return 5;
}

function customerLoginRateLimitMessage(): string
{
    return 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau 15 phút.';
}

function customerLoginIdentifier(string $identifier): string
{
    $identifier = trim($identifier);
    if (str_contains($identifier, '@')) {
        return mb_strtolower($identifier);
    }

    return phoneNormalize($identifier);
}

function customerLoginIsRateLimited(mysqli $conn, string $identifier): bool
{
    $key = customerLoginIdentifier($identifier);
    if ($key === '') {
        return false;
    }
    $stmt = $conn->prepare('SELECT attempts, window_start FROM login_attempts WHERE identifier = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }
    $since = strtotime((string) $row['window_start']) ?: 0;
    if ($since <= 0 || (time() - $since) >= customerLoginRateLimitWindowSeconds()) {
        customerLoginClearAttempts($conn, $identifier);
        return false;
    }

    return (int) $row['attempts'] >= customerLoginRateLimitMaxAttempts();
}

function customerLoginRecordFailure(mysqli $conn, string $identifier): void
{
    $key = customerLoginIdentifier($identifier);
    if ($key === '') {
        return;
    }
    $window = customerLoginRateLimitWindowSeconds();
    $stmt = $conn->prepare('SELECT attempts, window_start FROM login_attempts WHERE identifier = ? LIMIT 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $now = date('Y-m-d H:i:s');
    if (!$row || (time() - (strtotime((string) $row['window_start']) ?: 0)) >= $window) {
        $replace = $conn->prepare('INSERT INTO login_attempts (identifier, attempts, window_start) VALUES (?, 1, ?)
                                   ON DUPLICATE KEY UPDATE attempts = 1, window_start = VALUES(window_start)');
        if ($replace) {
            $replace->bind_param('ss', $key, $now);
            $replace->execute();
            $replace->close();
        }
        return;
    }
    $update = $conn->prepare('UPDATE login_attempts SET attempts = attempts + 1 WHERE identifier = ?');
    if ($update) {
        $update->bind_param('s', $key);
        $update->execute();
        $update->close();
    }
}

function customerLoginClearAttempts(mysqli $conn, string $identifier): void
{
    $key = customerLoginIdentifier($identifier);
    if ($key === '') {
        return;
    }
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE identifier = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->close();
}

function customerRegister(mysqli $conn, string $fullName, string $phone, string $email, string $password): array
{
    $fullName = trim($fullName);
    $phone = trim($phone);
    $email = trim($email);

    if ($fullName === '' || $phone === '' || $password === '') {
        return ['ok' => false, 'message' => 'Vui lòng nhập đầy đủ tên đăng nhập, số điện thoại và mật khẩu.'];
    }
    if (!phoneIsValidVietnamMobile($phone)) {
        return ['ok' => false, 'message' => 'Số điện thoại phải có đúng 10 số và bắt đầu bằng số 0.'];
    }
    $phone = phoneNormalize($phone);

    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự.'];
    }

    $stmtExists = $conn->prepare("SELECT id FROM customers WHERE phone = ? OR (email = ? AND email IS NOT NULL)");
    if (!$stmtExists) {
        return ['ok' => false, 'message' => 'Không kiểm tra được tài khoản hiện có.'];
    }
    $emailParam = $email !== '' ? $email : null;
    $stmtExists->bind_param('ss', $phone, $emailParam);
    $stmtExists->execute();
    $exists = $stmtExists->get_result()->fetch_assoc();
    $stmtExists->close();
    if ($exists) {
        return ['ok' => false, 'message' => 'Số điện thoại hoặc email đã tồn tại.'];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $customerCode = customerGenerateCode();
    $status = 'active';
    $stmt = $conn->prepare("INSERT INTO customers (customer_code, full_name, phone, email, password_hash, status, role)
                            VALUES (?, ?, ?, ?, ?, ?, 'customer')");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Không thể tạo tài khoản.'];
    }
    $stmt->bind_param('ssssss', $customerCode, $fullName, $phone, $emailParam, $passwordHash, $status);
    $stmt->execute();
    $customerId = (int) $stmt->insert_id;
    $stmt->close();

    $_SESSION['customer_id'] = $customerId;
    session_regenerate_id(true);
    require_once __DIR__ . '/cart-store.php';
    cartMergeGuestIntoCustomer($customerId);
    $linked = customerLinkOrdersByPhone($conn, $customerId, $phone);
    $message = 'Đăng ký thành công.';
    if ($linked > 0) {
        $message .= ' Đã gắn ' . $linked . ' đơn hàng trước đó vào tài khoản của bạn.';
    }
    return ['ok' => true, 'message' => $message, 'customer_id' => $customerId];
}

/**
 * Gắn đơn guest (cùng SĐT) vào tài khoản khách.
 */
function customerLinkOrdersByPhone(mysqli $conn, int $customerId, string $phone): int
{
    $normalized = phoneNormalize($phone);
    if ($customerId <= 0 || $normalized === '') {
        return 0;
    }

    $stmt = $conn->prepare('UPDATE orders SET customer_id = ?, updated_at = NOW()
                            WHERE customer_id IS NULL
                              AND REPLACE(REPLACE(REPLACE(customer_phone, " ", ""), "-", ""), ".", "") = ?');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('is', $customerId, $normalized);
    $stmt->execute();
    $linked = $stmt->affected_rows;
    $stmt->close();
    return max(0, $linked);
}

function customerLogin(mysqli $conn, string $identifier, string $password): array
{
    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        return ['ok' => false, 'message' => 'Vui lòng nhập SĐT/email và mật khẩu.'];
    }
    if (customerLoginIsRateLimited($conn, $identifier)) {
        return ['ok' => false, 'message' => customerLoginRateLimitMessage()];
    }

    $stmt = $conn->prepare("SELECT id, password_hash, status, role, full_name FROM customers
                            WHERE phone = ? OR email = ?
                            LIMIT 1");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Không thể đăng nhập lúc này.'];
    }
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row || !$row['password_hash'] || !password_verify($password, $row['password_hash'])) {
        customerLoginRecordFailure($conn, $identifier);
        if (customerLoginIsRateLimited($conn, $identifier)) {
            return ['ok' => false, 'message' => customerLoginRateLimitMessage()];
        }
        return ['ok' => false, 'message' => 'Thông tin đăng nhập không chính xác.'];
    }
    if ($row['status'] !== 'active') {
        return ['ok' => false, 'message' => 'Tài khoản hiện không khả dụng.'];
    }

    $_SESSION['customer_id'] = (int) $row['id'];
    session_regenerate_id(true);
    customerLoginClearAttempts($conn, $identifier);
    require_once __DIR__ . '/cart-store.php';
    cartMergeGuestIntoCustomer((int) $row['id']);
    require_once __DIR__ . '/admin-auth.php';
    $customer = customerGetById($conn, (int) $row['id']);
    adminSyncSessionForCustomer($customer);

    if (customerIsAdminRole($customer)) {
        return ['ok' => true, 'message' => 'Đăng nhập quản trị thành công.', 'is_admin' => true];
    }

    return ['ok' => true, 'message' => 'Đăng nhập thành công.', 'is_admin' => false];
}

function customerLogout(): void
{
    unset($_SESSION['customer_id'], $_SESSION['cart_session_key'], $_SESSION['cart_coupon'], $_SESSION['cart_coupon_id']);
    require_once __DIR__ . '/admin-auth.php';
    adminLogout();
}

/**
 * Cập nhật thông tin khách đang đăng nhập.
 */
function customerUpdateProfile(
    mysqli $conn,
    int $customerId,
    string $fullName,
    string $phone,
    string $email,
    string $newPassword = ''
): array {
    $fullName = trim($fullName);
    $phone = trim($phone);
    $email = trim($email);
    $newPassword = trim($newPassword);

    if ($customerId <= 0) {
        return ['ok' => false, 'message' => 'Bạn cần đăng nhập để cập nhật thông tin.'];
    }
    if ($fullName === '' || $phone === '') {
        return ['ok' => false, 'message' => 'Vui lòng nhập tên và số điện thoại.'];
    }
    if (!phoneIsValidVietnamMobile($phone)) {
        return ['ok' => false, 'message' => 'Số điện thoại phải có đúng 10 số và bắt đầu bằng số 0.'];
    }
    $phone = phoneNormalize($phone);
    if ($newPassword !== '' && strlen($newPassword) < 6) {
        return ['ok' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự.'];
    }

    $emailParam = $email !== '' ? $email : null;

    $stmtPhone = $conn->prepare('SELECT id FROM customers WHERE id <> ? AND phone = ? LIMIT 1');
    if (!$stmtPhone) {
        return ['ok' => false, 'message' => 'Không kiểm tra được thông tin trùng lặp.'];
    }
    $stmtPhone->bind_param('is', $customerId, $phone);
    $stmtPhone->execute();
    if ($stmtPhone->get_result()->fetch_assoc()) {
        $stmtPhone->close();
        return ['ok' => false, 'message' => 'Số điện thoại đã được tài khoản khác sử dụng.'];
    }
    $stmtPhone->close();

    if ($emailParam !== null) {
        $stmtEmail = $conn->prepare('SELECT id FROM customers WHERE id <> ? AND email = ? LIMIT 1');
        if (!$stmtEmail) {
            return ['ok' => false, 'message' => 'Không kiểm tra được thông tin trùng lặp.'];
        }
        $stmtEmail->bind_param('is', $customerId, $emailParam);
        $stmtEmail->execute();
        if ($stmtEmail->get_result()->fetch_assoc()) {
            $stmtEmail->close();
            return ['ok' => false, 'message' => 'Email đã được tài khoản khác sử dụng.'];
        }
        $stmtEmail->close();
    }

    if ($newPassword !== '') {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            'UPDATE customers SET full_name = ?, phone = ?, email = ?, password_hash = ? WHERE id = ? AND status = ?'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Không thể cập nhật tài khoản.'];
        }
        $status = 'active';
        $stmt->bind_param('ssssis', $fullName, $phone, $emailParam, $hash, $customerId, $status);
    } else {
        $stmt = $conn->prepare(
            'UPDATE customers SET full_name = ?, phone = ?, email = ? WHERE id = ? AND status = ?'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Không thể cập nhật tài khoản.'];
        }
        $status = 'active';
        $stmt->bind_param('sssis', $fullName, $phone, $emailParam, $customerId, $status);
    }

    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'message' => 'Không thể cập nhật tài khoản.'];
    }

    return ['ok' => true, 'message' => 'Đã cập nhật thông tin tài khoản.'];
}

/**
 * Khách có thể mua trên storefront (trừ phi đang đăng nhập quản trị cùng phiên).
 */
function customerMayShopOnStorefront(?array $customer): bool
{
    if ($customer === null) {
        return true;
    }

    return ($customer['role'] ?? 'customer') !== 'admin';
}

function storefrontMayAddToCart(?array $customer): bool
{
    require_once __DIR__ . '/admin-auth.php';
    if (adminCurrent()) {
        return false;
    }

    return customerMayShopOnStorefront($customer);
}
