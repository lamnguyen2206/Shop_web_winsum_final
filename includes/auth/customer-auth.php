<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers.php';

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
        $conn->query(
            "ALTER TABLE customers ADD COLUMN role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer' AFTER status"
        );
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

    $code = 'ADM00001';
    $name = 'admin';
    $phone = '0901000000';
    $email = 'admin@winsumhome.vn';
    $hash = '$2y$10$LiwDdPmNl40o4nDcOw8H9O4UyStaVDFyclvHztxZjxfEP6T4Fna3K';
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

    require_once __DIR__ . '/../admin-auth.php';
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

function customerLoginIsRateLimited(): bool
{
    $attempts = $_SESSION['login_attempts'] ?? null;
    if (!is_array($attempts)) {
        return false;
    }

    $count = (int) ($attempts['count'] ?? 0);
    $since = (int) ($attempts['since'] ?? 0);
    if ($since <= 0) {
        return false;
    }

    if ((time() - $since) >= customerLoginRateLimitWindowSeconds()) {
        unset($_SESSION['login_attempts']);
        return false;
    }

    return $count >= customerLoginRateLimitMaxAttempts();
}

function customerLoginRecordFailure(): void
{
    $attempts = $_SESSION['login_attempts'] ?? null;
    if (!is_array($attempts)) {
        $_SESSION['login_attempts'] = ['count' => 1, 'since' => time()];
        return;
    }

    $since = (int) ($attempts['since'] ?? 0);
    if ($since <= 0 || (time() - $since) >= customerLoginRateLimitWindowSeconds()) {
        $_SESSION['login_attempts'] = ['count' => 1, 'since' => time()];
        return;
    }

    $_SESSION['login_attempts']['count'] = (int) ($attempts['count'] ?? 0) + 1;
}

function customerLoginClearAttempts(): void
{
    unset($_SESSION['login_attempts']);
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
    customerLoginClearAttempts();
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
    if (customerLoginIsRateLimited()) {
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
        customerLoginRecordFailure();
        if (customerLoginIsRateLimited()) {
            return ['ok' => false, 'message' => customerLoginRateLimitMessage()];
        }
        return ['ok' => false, 'message' => 'Thông tin đăng nhập không chính xác.'];
    }
    if ($row['status'] !== 'active') {
        return ['ok' => false, 'message' => 'Tài khoản hiện không khả dụng.'];
    }

    $_SESSION['customer_id'] = (int) $row['id'];
    session_regenerate_id(true);
    customerLoginClearAttempts();
    require_once __DIR__ . '/../admin-auth.php';
    $customer = customerGetById($conn, (int) $row['id']);
    adminSyncSessionForCustomer($customer);

    if (customerIsAdminRole($customer)) {
        return ['ok' => true, 'message' => 'Đăng nhập quản trị thành công.', 'is_admin' => true];
    }

    return ['ok' => true, 'message' => 'Đăng nhập thành công.', 'is_admin' => false];
}

function customerLogout(): void
{
    require_once __DIR__ . '/../cart-store.php';
    cartClear();
    unset($_SESSION['customer_id']);
    require_once __DIR__ . '/../admin-auth.php';
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
