<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function couponGetByCode(mysqli $conn, string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }

    $fields = couponSelectFields($conn);
    $stmt = $conn->prepare("SELECT {$fields} FROM coupons WHERE code = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function couponCountCustomerUses(mysqli $conn, int $couponId, ?int $customerId): int
{
    if ($customerId === null || $customerId <= 0) {
        return 0;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) AS total
                            FROM coupon_redemptions cr
                            INNER JOIN orders o ON o.id = cr.order_id
                            WHERE cr.coupon_id = ?
                              AND cr.customer_id = ?
                              AND o.status NOT IN ('cancelled', 'returned')");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('ii', $couponId, $customerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['total'] ?? 0);
}

function couponCountUsesByPhone(mysqli $conn, int $couponId, string $phone): int
{
    $normalized = phoneNormalize($phone);
    if ($normalized === '') {
        return 0;
    }

    $stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) AS total
                            FROM orders o
                            WHERE o.coupon_id = ?
                              AND REPLACE(REPLACE(REPLACE(o.customer_phone, ' ', ''), '-', ''), '.', '') = ?
                              AND o.status NOT IN ('cancelled', 'returned')");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('is', $couponId, $normalized);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['total'] ?? 0);
}

/** Khóa giới hạn mã cho khách vãng lai (theo phiên, không cần SĐT). */
function couponGuestLimitKey(): string
{
    return session_id() !== '' ? 'sid:' . session_id() : '';
}

/** Số đơn đã đặt trong phiên trình duyệt (guest) với mã. */
function couponCountSessionUsesForPhone(int $couponId, string $phone): int
{
    $normalized = phoneNormalize($phone);
    if ($normalized === '' || empty($_SESSION['coupon_order_uses']) || !is_array($_SESSION['coupon_order_uses'])) {
        return 0;
    }
    return (int) ($_SESSION['coupon_order_uses'][$couponId][$normalized] ?? 0);
}

function couponRecordSessionOrderUseForPhone(int $couponId, string $phone): void
{
    $normalized = phoneNormalize($phone);
    if ($normalized === '') {
        return;
    }
    if (!isset($_SESSION['coupon_order_uses']) || !is_array($_SESSION['coupon_order_uses'])) {
        $_SESSION['coupon_order_uses'] = [];
    }
    if (!isset($_SESSION['coupon_order_uses'][$couponId]) || !is_array($_SESSION['coupon_order_uses'][$couponId])) {
        $_SESSION['coupon_order_uses'][$couponId] = [];
    }
    $_SESSION['coupon_order_uses'][$couponId][$normalized] = ($_SESSION['coupon_order_uses'][$couponId][$normalized] ?? 0) + 1;
}

/** Đã áp mã vào giỏ trong phiên (chưa đặt hàng) — chặn áp lại vượt giới hạn. */
function couponHasSessionCartApply(int $couponId, string $phone): bool
{
    $normalized = phoneNormalize($phone);
    if ($normalized === '') {
        return false;
    }
    return !empty($_SESSION['coupon_cart_applied'][$couponId][$normalized]);
}

function couponMarkSessionCartApply(int $couponId, string $phone): void
{
    $normalized = phoneNormalize($phone);
    if ($normalized === '') {
        return;
    }
    if (!isset($_SESSION['coupon_cart_applied']) || !is_array($_SESSION['coupon_cart_applied'])) {
        $_SESSION['coupon_cart_applied'] = [];
    }
    $_SESSION['coupon_cart_applied'][$couponId][$normalized] = true;
}

function couponCountTotalUses(mysqli $conn, int $couponId): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS total
                            FROM coupon_redemptions cr
                            INNER JOIN orders o ON o.id = cr.order_id
                            WHERE cr.coupon_id = ?
                              AND o.status NOT IN ('cancelled', 'returned')");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $couponId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['total'] ?? 0);
}

function couponAssertAvailableInTransaction(
    mysqli $conn,
    int $couponId,
    ?int $customerId,
    string $phone
): void {
    $stmt = $conn->prepare("SELECT id, per_customer_limit, total_usage_limit
                            FROM coupons
                            WHERE id = ?
                            LIMIT 1
                            FOR UPDATE");
    if (!$stmt) {
        throw new RuntimeException('Không kiểm tra được mã giảm giá.');
    }
    $stmt->bind_param('i', $couponId);
    $stmt->execute();
    $coupon = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$coupon) {
        throw new RuntimeException('Mã giảm giá không còn hiệu lực.');
    }

    $perCustomerLimit = $coupon['per_customer_limit'] !== null ? (int) $coupon['per_customer_limit'] : null;
    if ($perCustomerLimit !== null) {
        $uses = 0;
        if ($customerId) {
            $uses = couponCountCustomerUses($conn, $couponId, $customerId);
        }
        $normalizedPhone = phoneNormalize($phone);
        if ($normalizedPhone !== '') {
            $uses = max($uses, couponCountUsesByPhone($conn, $couponId, $normalizedPhone));
        }
        if ($uses >= $perCustomerLimit) {
            throw new RuntimeException('Bạn đã sử dụng hết lượt cho mã giảm giá này.');
        }
    }

    $totalLimit = $coupon['total_usage_limit'] !== null ? (int) $coupon['total_usage_limit'] : null;
    if ($totalLimit !== null && couponCountTotalUses($conn, $couponId) >= $totalLimit) {
        throw new RuntimeException('Mã giảm giá đã hết lượt sử dụng.');
    }
}

function couponReleaseRedemption(mysqli $conn, int $orderId): void
{
    if ($orderId <= 0) {
        return;
    }
    $stmt = $conn->prepare('DELETE FROM coupon_redemptions WHERE order_id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $stmt->close();
}

function couponValidate(mysqli $conn, string $code, float $subtotal, ?int $customerId, string $guestPhone = ''): array
{
    $coupon = couponGetByCode($conn, $code);
    if (!$coupon) {
        return ['ok' => false, 'message' => 'Mã giảm giá không tồn tại.'];
    }
    if ((int) $coupon['is_active'] !== 1) {
        return ['ok' => false, 'message' => 'Mã giảm giá không còn hiệu lực.'];
    }

    $now = time();
    if (!empty($coupon['starts_at']) && strtotime((string) $coupon['starts_at']) > $now) {
        return ['ok' => false, 'message' => 'Mã giảm giá chưa đến thời gian áp dụng.'];
    }
    if (!empty($coupon['ends_at']) && strtotime((string) $coupon['ends_at']) < $now) {
        return ['ok' => false, 'message' => 'Mã giảm giá đã hết hạn.'];
    }

    $minOrder = (float) ($coupon['min_order_amount'] ?? 0);
    if ($minOrder > 0 && $subtotal < $minOrder) {
        return ['ok' => false, 'message' => 'Đơn hàng chưa đạt giá trị tối thiểu để dùng mã này.'];
    }

    $couponId = (int) $coupon['id'];
    $perCustomerLimit = $coupon['per_customer_limit'] !== null ? (int) $coupon['per_customer_limit'] : null;
    if ($perCustomerLimit !== null) {
        $uses = 0;
        if ($customerId) {
            $uses = couponCountCustomerUses($conn, $couponId, $customerId);
        }
        $phone = phoneNormalize($guestPhone);
        if ($phone === '' && !$customerId) {
            $phone = couponGuestLimitKey();
        }
        if ($phone !== '') {
            if (!str_starts_with($phone, 'sid:')) {
                $uses = max($uses, couponCountUsesByPhone($conn, $couponId, $phone));
            }
            $uses = max($uses, couponCountSessionUsesForPhone($couponId, $phone));
        }
        if ($uses >= $perCustomerLimit) {
            return ['ok' => false, 'message' => 'Bạn đã sử dụng hết lượt cho mã giảm giá này.'];
        }
    }

    $totalLimit = $coupon['total_usage_limit'] !== null ? (int) $coupon['total_usage_limit'] : null;
    if ($totalLimit !== null && couponCountTotalUses($conn, $couponId) >= $totalLimit) {
        return ['ok' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng.'];
    }

    return ['ok' => true, 'message' => 'Áp mã thành công.', 'coupon' => $coupon];
}

function couponCalculateDiscount(array $coupon, float $subtotal, float $shipping): float
{
    $type = (string) $coupon['discount_type'];
    $value = (float) $coupon['discount_value'];
    $discount = 0.0;

    if ($type === 'fixed') {
        $discount = $value;
    } elseif ($type === 'percent') {
        $discount = $subtotal * ($value / 100);
        $maxDiscount = $coupon['max_discount_amount'] !== null ? (float) $coupon['max_discount_amount'] : null;
        if ($maxDiscount !== null) {
            $discount = min($discount, $maxDiscount);
        }
    } elseif ($type === 'shipping') {
        $discount = min($shipping, $value > 0 ? $value : $shipping);
    }

    return max(0, min($discount, $subtotal + $shipping));
}

function couponRecordRedemption(mysqli $conn, int $couponId, ?int $customerId, int $orderId): void
{
    $stmt = $conn->prepare("INSERT INTO coupon_redemptions (coupon_id, customer_id, order_id) VALUES (?, ?, ?)");
    if (!$stmt) {
        return;
    }
    $customerIdValue = $customerId ?: null;
    $stmt->bind_param('iii', $couponId, $customerIdValue, $orderId);
    $stmt->execute();
    $stmt->close();
}

/** Mã giảm giá đang áp dụng trong giỏ (mỗi đơn tối đa một mã). */
function couponGetAppliedCode(): string
{
    return strtoupper(trim((string) ($_SESSION['cart_coupon'] ?? '')));
}

/** Cột SELECT coupons — có coupon_role khi đã migrate. */
function couponSelectFields(mysqli $conn): string
{
    static $fields = null;
    if ($fields !== null) {
        return $fields;
    }

    $base = 'id, code, name, description, discount_type, discount_value, min_order_amount,
             max_discount_amount, total_usage_limit, per_customer_limit, starts_at, ends_at, is_active';
    $fields = $base;

    $check = $conn->query("SHOW COLUMNS FROM coupons LIKE 'coupon_role'");
    if ($check && $check->num_rows > 0) {
        $fields = 'id, code, name, description, discount_type, coupon_role, discount_value, min_order_amount,
                   max_discount_amount, total_usage_limit, per_customer_limit, starts_at, ends_at, is_active';
    }
    if ($check) {
        $check->free();
    }

    return preg_replace('/\s+/', ' ', trim($fields)) ?: $base;
}

function couponFormatMoneyShort(float $amount): string
{
    if ($amount >= 1000000) {
        $millions = $amount / 1000000;
        $formatted = number_format($millions, 1, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted . 'tr';
    }
    if ($amount >= 1000) {
        return (string) (int) round($amount / 1000) . 'k';
    }

    return number_format($amount, 0, ',', '.') . 'đ';
}

/** @return array<string, string> Tab filter key => nhãn hiển thị */
function couponRoleDefinitions(): array
{
    return [
        'discount' => 'Mã giảm giá',
        'shipping' => 'Freeship',
        'vip' => 'VIP',
    ];
}

function couponNormalizeRole(string $role): string
{
    $role = strtolower(trim($role));
    return array_key_exists($role, couponRoleDefinitions()) ? $role : 'discount';
}

/** Suy luận role khi DB chưa có cột coupon_role (tương thích cũ). */
function couponInferRoleFromCoupon(array $coupon): string
{
    if ((string) ($coupon['discount_type'] ?? '') === 'shipping') {
        return 'shipping';
    }
    $code = strtoupper((string) ($coupon['code'] ?? ''));
    $name = strtoupper((string) ($coupon['name'] ?? ''));
    if (str_contains($code, 'VIP') || str_contains($name, 'VIP')) {
        return 'vip';
    }

    return 'discount';
}

/** Role chính thức từ cột coupons.coupon_role. */
function couponResolveRole(array $coupon): string
{
    if (isset($coupon['coupon_role']) && (string) $coupon['coupon_role'] !== '') {
        return couponNormalizeRole((string) $coupon['coupon_role']);
    }

    return couponInferRoleFromCoupon($coupon);
}

function couponGetRoleLabel(string $role): string
{
    $definitions = couponRoleDefinitions();

    return $definitions[couponNormalizeRole($role)] ?? $definitions['discount'];
}

/** Khóa tab filter (discount | shipping | vip) — đọc từ coupon_role. */
function couponGetTabCategory(array $coupon): string
{
    return couponResolveRole($coupon);
}

function couponFormatDisplayAmount(array $coupon): string
{
    $type = (string) ($coupon['discount_type'] ?? 'fixed');
    $value = (float) ($coupon['discount_value'] ?? 0);

    if ($type === 'fixed') {
        return number_format($value, 0, ',', '.') . 'đ';
    }
    if ($type === 'percent') {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . '%';
    }
    if ($type === 'shipping') {
        return 'FREE';
    }

    return '';
}

function couponFormatShortSummary(array $coupon): string
{
    $type = (string) ($coupon['discount_type'] ?? 'fixed');
    $value = (float) ($coupon['discount_value'] ?? 0);
    $min = (float) ($coupon['min_order_amount'] ?? 0);

    if ($type === 'fixed') {
        $line = 'Giảm ' . number_format($value, 0, ',', '.') . 'đ';
        if ($min > 0) {
            $line .= ' cho đơn từ ' . couponFormatMoneyShort($min);
        }
        return $line;
    }
    if ($type === 'percent') {
        $pct = rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
        $line = 'Giảm ' . $pct . '%';
        if ($min > 0) {
            $line .= ' cho đơn từ ' . couponFormatMoneyShort($min);
        }
        if ($coupon['max_discount_amount'] !== null && (float) $coupon['max_discount_amount'] > 0) {
            $line .= ' (Tối đa ' . couponFormatMoneyShort((float) $coupon['max_discount_amount']) . ')';
        }
        return $line;
    }
    if ($type === 'shipping') {
        $line = 'Freeship';
        if ($min > 0) {
            $line .= ' đơn từ ' . couponFormatMoneyShort($min);
        }
        return $line;
    }

    return '';
}

function couponFormatDetailText(array $coupon, mysqli $conn): string
{
    $parts = [];
    if (!empty($coupon['description'])) {
        $parts[] = trim((string) $coupon['description']);
    }
    if ($coupon['per_customer_limit'] !== null) {
        $parts[] = 'Giới hạn ' . (int) $coupon['per_customer_limit'] . ' lượt/khách.';
    }
    if ($coupon['total_usage_limit'] !== null) {
        $used = couponCountTotalUses($conn, (int) $coupon['id']);
        $remaining = max(0, (int) $coupon['total_usage_limit'] - $used);
        $parts[] = 'Còn ' . $remaining . '/' . (int) $coupon['total_usage_limit'] . ' lượt toàn hệ thống.';
    }
    if (!empty($coupon['starts_at'])) {
        $parts[] = 'Bắt đầu: ' . date('d/m/Y H:i', strtotime((string) $coupon['starts_at'])) . '.';
    }
    if (!empty($coupon['ends_at'])) {
        $parts[] = 'Hết hạn: ' . date('d/m/Y H:i', strtotime((string) $coupon['ends_at'])) . '.';
    }

    return implode(' ', $parts);
}

function couponGetUrgencyBadge(mysqli $conn, array $coupon): ?string
{
    if ($coupon['total_usage_limit'] !== null) {
        $used = couponCountTotalUses($conn, (int) $coupon['id']);
        $remaining = max(0, (int) $coupon['total_usage_limit'] - $used);
        if ($remaining > 0 && $remaining <= 20) {
            return 'Chỉ còn ' . $remaining . ' lượt';
        }
    }

    if (!empty($coupon['ends_at'])) {
        $endsAt = strtotime((string) $coupon['ends_at']);
        if ($endsAt !== false) {
            $daysLeft = ($endsAt - time()) / 86400;
            if ($daysLeft > 0 && $daysLeft <= 7) {
                return 'Sắp hết hạn';
            }
        }
    }

    return null;
}

function couponGetExpiryHint(array $coupon): string
{
    if (!empty($coupon['ends_at'])) {
        return 'HSD ' . date('d/m/Y', strtotime((string) $coupon['ends_at']));
    }

    return 'Không giới hạn thời gian';
}

function couponFormatBenefitLabel(array $coupon): string
{
    $type = (string) ($coupon['discount_type'] ?? 'fixed');
    $value = (float) ($coupon['discount_value'] ?? 0);

    if ($type === 'fixed') {
        return 'Giảm ' . number_format($value, 0, ',', '.') . 'đ';
    }
    if ($type === 'percent') {
        $label = 'Giảm ' . rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . '%';
        if ($coupon['max_discount_amount'] !== null && (float) $coupon['max_discount_amount'] > 0) {
            $label .= ' (tối đa ' . number_format((float) $coupon['max_discount_amount'], 0, ',', '.') . 'đ)';
        }
        return $label;
    }
    if ($type === 'shipping') {
        return 'Giảm phí vận chuyển' . ($value > 0 ? ' (tối đa ' . number_format($value, 0, ',', '.') . 'đ)' : '');
    }

    return 'Ưu đãi';
}

/**
 * Danh sách mã đang bật từ bảng coupons (lọc sơ bộ thời hạn).
 *
 * @return list<array<string, mixed>>
 */
function couponListActive(mysqli $conn): array
{
    $fields = couponSelectFields($conn);
    $orderBy = str_contains($fields, 'coupon_role')
        ? 'coupon_role ASC, id ASC'
        : 'id ASC';
    $result = $conn->query("SELECT {$fields} FROM coupons WHERE is_active = 1 ORDER BY {$orderBy}");
    if (!$result) {
        return [];
    }

    $now = time();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['starts_at']) && strtotime((string) $row['starts_at']) > $now) {
            continue;
        }
        if (!empty($row['ends_at']) && strtotime((string) $row['ends_at']) < $now) {
            continue;
        }
        $rows[] = $row;
    }
    $result->free();

    return $rows;
}

/**
 * Gợi ý mã khách có thể áp dụng theo giá trị giỏ hàng hiện tại.
 *
 * @return list<array<string, mixed>>
 */
function couponGetSuggestions(
    mysqli $conn,
    float $subtotal,
    float $shipping,
    ?int $customerId,
    string $guestPhone = ''
): array {
    $appliedCode = couponGetAppliedCode();
    $suggestions = [];

    foreach (couponListActive($conn) as $coupon) {
        $code = strtoupper(trim((string) $coupon['code']));
        $validation = couponValidate($conn, $code, $subtotal, $customerId, $guestPhone);
        $canApply = !empty($validation['ok']);
        $estimatedDiscount = 0;
        if ($canApply) {
            $estimatedDiscount = (int) round(couponCalculateDiscount($coupon, $subtotal, $shipping));
        }

        $minOrder = (float) ($coupon['min_order_amount'] ?? 0);
        $shortfall = ($minOrder > 0 && $subtotal < $minOrder)
            ? (int) ceil($minOrder - $subtotal)
            : 0;
        $progressPercent = 0;
        if ($minOrder > 0 && $subtotal > 0) {
            $progressPercent = (int) min(100, floor(($subtotal / $minOrder) * 100));
        }
        if ($canApply) {
            $progressPercent = 100;
        }

        $role = couponResolveRole($coupon);
        $suggestions[] = [
            'id' => (int) $coupon['id'],
            'code' => $code,
            'name' => (string) ($coupon['name'] ?? $code),
            'description' => trim((string) ($coupon['description'] ?? '')),
            'discount_type' => (string) ($coupon['discount_type'] ?? 'fixed'),
            'coupon_role' => $role,
            'role_label' => couponGetRoleLabel($role),
            'tab' => $role,
            'display_amount' => couponFormatDisplayAmount($coupon),
            'short_summary' => couponFormatShortSummary($coupon),
            'detail_text' => couponFormatDetailText($coupon, $conn),
            'expiry_hint' => couponGetExpiryHint($coupon),
            'urgency_badge' => couponGetUrgencyBadge($conn, $coupon),
            'benefit_label' => couponFormatBenefitLabel($coupon),
            'can_apply' => $canApply,
            'is_applied' => $appliedCode !== '' && $code === $appliedCode,
            'is_locked' => !$canApply && $shortfall > 0,
            'estimated_discount' => $estimatedDiscount,
            'message' => (string) ($validation['message'] ?? ''),
            'shortfall' => $shortfall,
            'progress_percent' => $progressPercent,
            'min_order_amount' => $minOrder,
        ];
    }

    usort($suggestions, static function (array $a, array $b): int {
        if ($a['is_applied'] !== $b['is_applied']) {
            return $b['is_applied'] <=> $a['is_applied'];
        }
        if ($a['can_apply'] !== $b['can_apply']) {
            return $b['can_apply'] <=> $a['can_apply'];
        }
        if ($a['can_apply'] && $b['can_apply']) {
            return $b['estimated_discount'] <=> $a['estimated_discount'];
        }
        if ($a['shortfall'] !== $b['shortfall']) {
            return $a['shortfall'] <=> $b['shortfall'];
        }
        return strcmp($a['code'], $b['code']);
    });

    return $suggestions;
}

/** Tìm mã giảm nhiều nhất cho giỏ hàng hiện tại. */
function couponFindBestDeal(
    mysqli $conn,
    float $subtotal,
    float $shipping,
    ?int $customerId,
    string $guestPhone = ''
): ?array {
    $bestCoupon = null;
    $bestDiscount = 0.0;

    foreach (couponListActive($conn) as $coupon) {
        $code = strtoupper(trim((string) $coupon['code']));
        $validation = couponValidate($conn, $code, $subtotal, $customerId, $guestPhone);
        if (empty($validation['ok'])) {
            continue;
        }
        $discount = couponCalculateDiscount($coupon, $subtotal, $shipping);
        if ($discount > $bestDiscount) {
            $bestDiscount = $discount;
            $bestCoupon = $coupon;
        }
    }

    return $bestCoupon;
}

/**
 * Tự áp mã tiết kiệm nhất nếu giỏ chưa có mã.
 * Trả về thông báo hiển thị hoặc null.
 */
function couponTryAutoApplyBestDeal(
    mysqli $conn,
    float $subtotal,
    float $shipping,
    ?int $customerId,
    string $guestPhone = ''
): ?string {
    if (couponGetAppliedCode() !== '') {
        return null;
    }
    if (!empty($_SESSION['cart_coupon_auto_skip'])) {
        return null;
    }
    if ($subtotal <= 0) {
        return null;
    }

    require_once __DIR__ . '/cart-store.php';

    $best = couponFindBestDeal($conn, $subtotal, $shipping, $customerId, $guestPhone);
    if ($best === null) {
        return null;
    }

    cartSetCoupon($best);
    if (!$customerId) {
        couponMarkSessionCartApply((int) $best['id'], couponGuestLimitKey());
    }

    $discount = (int) round(couponCalculateDiscount($best, $subtotal, $shipping));
    $code = strtoupper((string) $best['code']);

    return 'Đã tự áp dụng mã ' . $code . ' — tiết kiệm ~' . number_format($discount, 0, ',', '.') . 'đ';
}
