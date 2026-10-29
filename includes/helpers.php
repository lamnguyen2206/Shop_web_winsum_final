<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url, int $status = 303): void
{
    header('Location: ' . $url, true, $status);
    exit;
}

function app_url(string $view, array $params = []): string
{
    $params['view'] = $view;
    return 'index.php?' . http_build_query($params);
}

/** URL mở form đăng nhập (modal) trên trang đích sau khi tải. */
function auth_login_url(string $returnView = 'home', array $params = []): string
{
    $params['auth'] = 'login';
    return app_url($returnView, $params);
}

/** URL mở form đăng ký (modal) trên trang đích sau khi tải. */
function auth_register_url(string $returnView = 'home', array $params = []): string
{
    $params['auth'] = 'register';
    return app_url($returnView, $params);
}

/** Chuẩn hóa SĐT để so khớp đơn hàng / mã giảm giá. */
function phoneNormalize(string $phone): string
{
    return preg_replace('/\D+/', '', trim($phone)) ?? '';
}

function phoneIsValidVietnamMobile(string $phone): bool
{
    return preg_match('/^0\d{9}$/', phoneNormalize($phone)) === 1;
}

/**
 * Giá trị dự phòng trong config/local.php khi chưa lưu trên web.
 *
 * @return array{code:string,account_number:string,account_name:string}
 */
function shopBankAccountFromConfig(): array
{
    $config = function_exists('appConfig') ? appConfig() : [];
    $bank = is_array($config['bank'] ?? null) ? $config['bank'] : [];

    return [
        'code' => trim((string) ($bank['code'] ?? '')),
        'account_number' => preg_replace('/\D+/', '', (string) ($bank['account_number'] ?? '')) ?? '',
        'account_name' => trim((string) ($bank['account_name'] ?? '')),
    ];
}

/**
 * Tài khoản nhận chuyển khoản. Ưu tiên bản admin đã lưu trong database.
 *
 * @return array{code:string,account_number:string,account_name:string}
 */
function shopBankAccount(): array
{
    global $conn;
    $fallback = shopBankAccountFromConfig();
    if (!$conn instanceof mysqli) {
        return $fallback;
    }

    try {
        $result = $conn->query('SELECT bank_code, account_number, account_name FROM shop_payment_accounts WHERE id = 1 LIMIT 1');
    } catch (mysqli_sql_exception) {
        return $fallback;
    }
    if (!$result) {
        return $fallback;
    }
    $row = $result->fetch_assoc();
    if (!$row) {
        return $fallback;
    }

    return [
        'code' => trim((string) ($row['bank_code'] ?? '')),
        'account_number' => preg_replace('/\D+/', '', (string) ($row['account_number'] ?? '')) ?? '',
        'account_name' => trim((string) ($row['account_name'] ?? '')),
    ];
}

/**
 * @return array{ok:bool,message:string}
 */
function shopBankAccountSave(mysqli $conn, string $bankCode, string $accountNumber, string $accountName): array
{
    $bankCode = strtoupper(trim($bankCode));
    $accountNumber = preg_replace('/\D+/', '', $accountNumber) ?? '';
    $accountName = trim($accountName);

    if (!preg_match('/^[A-Z0-9]{2,20}$/', $bankCode)) {
        return ['ok' => false, 'message' => 'Mã ngân hàng gồm 2–20 ký tự, ví dụ MB, VCB, BIDV.'];
    }
    if (!preg_match('/^\d{6,19}$/', $accountNumber)) {
        return ['ok' => false, 'message' => 'Số tài khoản chỉ gồm chữ số, từ 6 đến 19 số.'];
    }
    if ($accountName === '' || mb_strlen($accountName) > 120) {
        return ['ok' => false, 'message' => 'Vui lòng nhập tên chủ tài khoản, tối đa 120 ký tự.'];
    }

    try {
        $stmt = $conn->prepare('INSERT INTO shop_payment_accounts (id, bank_code, account_number, account_name)
                                VALUES (1, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                    bank_code = VALUES(bank_code),
                                    account_number = VALUES(account_number),
                                    account_name = VALUES(account_name)');
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Chưa có bảng tài khoản nhận tiền. Hãy chạy php scripts/migrate.php.'];
        }
        $stmt->bind_param('sss', $bankCode, $accountNumber, $accountName);
        $ok = $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception) {
        return ['ok' => false, 'message' => 'Chưa có bảng tài khoản nhận tiền. Hãy chạy php scripts/migrate.php.'];
    }
    if (!$ok) {
        return ['ok' => false, 'message' => 'Không lưu được tài khoản nhận tiền.'];
    }

    return ['ok' => true, 'message' => 'Đã cập nhật tài khoản nhận chuyển khoản.'];
}
