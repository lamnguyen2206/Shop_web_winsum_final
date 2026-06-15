<?php

declare(strict_types=1);

require_once __DIR__ . '/coupon-repository.php';

function couponAdminHasRoleColumn(mysqli $conn): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    $check = $conn->query("SHOW COLUMNS FROM coupons LIKE 'coupon_role'");
    $has = $check && $check->num_rows > 0;
    if ($check) {
        $check->free();
    }

    return $has;
}

/**
 * @return list<array<string, mixed>>
 */
function couponAdminList(mysqli $conn, int $limit = 200): array
{
    $fields = couponSelectFields($conn);
    $result = $conn->query("SELECT {$fields}, created_at, updated_at FROM coupons ORDER BY id DESC LIMIT " . (int) $limit);
    if (!$result) {
        return [];
    }

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $row['role_label'] = couponGetRoleLabel(couponResolveRole($row));
        $row['short_summary'] = couponFormatShortSummary($row);
        $row['uses_count'] = couponCountTotalUses($conn, (int) $row['id']);
        $items[] = $row;
    }
    $result->free();

    return $items;
}

function couponAdminGetById(mysqli $conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $fields = couponSelectFields($conn);
    $stmt = $conn->prepare("SELECT {$fields}, created_at, updated_at FROM coupons WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function couponAdminNormalizeCode(string $code): string
{
    return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
}

function couponAdminCodeExists(mysqli $conn, string $code, int $excludeId = 0): bool
{
    $code = couponAdminNormalizeCode($code);
    if ($code === '') {
        return false;
    }

    if ($excludeId > 0) {
        $stmt = $conn->prepare('SELECT id FROM coupons WHERE code = ? AND id <> ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $code, $excludeId);
    } else {
        $stmt = $conn->prepare('SELECT id FROM coupons WHERE code = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $code);
    }
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $exists;
}

/**
 * @param array<string, mixed> $data
 * @return array{ok: bool, message: string, coupon_id?: int}
 */
function couponAdminSave(mysqli $conn, array $data): array
{
    $id = (int) ($data['id'] ?? 0);
    $code = couponAdminNormalizeCode((string) ($data['code'] ?? ''));
    $name = trim((string) ($data['name'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $discountType = (string) ($data['discount_type'] ?? 'fixed');
    $couponRole = couponNormalizeRole((string) ($data['coupon_role'] ?? 'discount'));
    $discountValue = (float) ($data['discount_value'] ?? 0);
    $isActive = !empty($data['is_active']) ? 1 : 0;

    if ($code === '') {
        return ['ok' => false, 'message' => 'Vui lòng nhập mã coupon.'];
    }
    if (strlen($code) > 50) {
        return ['ok' => false, 'message' => 'Mã coupon tối đa 50 ký tự.'];
    }
    if ($name === '') {
        return ['ok' => false, 'message' => 'Vui lòng nhập tên hiển thị.'];
    }
    if (!in_array($discountType, ['fixed', 'percent', 'shipping'], true)) {
        return ['ok' => false, 'message' => 'Loại giảm giá không hợp lệ.'];
    }

    if ($discountType === 'shipping') {
        $couponRole = 'shipping';
    } elseif ($couponRole === 'shipping') {
        $couponRole = 'discount';
    }

    if ($discountValue <= 0) {
        return ['ok' => false, 'message' => 'Giá trị giảm phải lớn hơn 0.'];
    }
    if ($discountType === 'percent' && $discountValue > 100) {
        return ['ok' => false, 'message' => 'Phần trăm giảm tối đa 100%.'];
    }

    if (couponAdminCodeExists($conn, $code, $id)) {
        return ['ok' => false, 'message' => 'Mã coupon đã tồn tại.'];
    }

    $minOrder = ($data['min_order_amount'] ?? '') !== ''
        ? (float) $data['min_order_amount']
        : null;
    $maxDiscount = ($data['max_discount_amount'] ?? '') !== ''
        ? (float) $data['max_discount_amount']
        : null;
    $totalLimit = ($data['total_usage_limit'] ?? '') !== ''
        ? (int) $data['total_usage_limit']
        : null;
    $perCustomerLimit = ($data['per_customer_limit'] ?? '') !== ''
        ? (int) $data['per_customer_limit']
        : null;

    $startsAt = couponAdminParseDatetime((string) ($data['starts_at'] ?? ''));
    $endsAt = couponAdminParseDatetime((string) ($data['ends_at'] ?? ''));

    if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) > strtotime($endsAt)) {
        return ['ok' => false, 'message' => 'Thời gian bắt đầu phải trước thời gian kết thúc.'];
    }

    $descriptionParam = $description !== '' ? $description : null;
    $hasRole = couponAdminHasRoleColumn($conn);

    if ($id > 0) {
        if ($hasRole) {
            $stmt = $conn->prepare('UPDATE coupons SET
                code = ?, name = ?, description = ?, discount_type = ?, coupon_role = ?,
                discount_value = ?, min_order_amount = ?, max_discount_amount = ?,
                total_usage_limit = ?, per_customer_limit = ?, starts_at = ?, ends_at = ?,
                is_active = ?, updated_at = NOW()
                WHERE id = ?');
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Không thể cập nhật mã giảm giá.'];
            }
            $stmt->bind_param(
                'sssssdddiissii',
                $code,
                $name,
                $descriptionParam,
                $discountType,
                $couponRole,
                $discountValue,
                $minOrder,
                $maxDiscount,
                $totalLimit,
                $perCustomerLimit,
                $startsAt,
                $endsAt,
                $isActive,
                $id
            );
        } else {
            $stmt = $conn->prepare('UPDATE coupons SET
                code = ?, name = ?, description = ?, discount_type = ?,
                discount_value = ?, min_order_amount = ?, max_discount_amount = ?,
                total_usage_limit = ?, per_customer_limit = ?, starts_at = ?, ends_at = ?,
                is_active = ?, updated_at = NOW()
                WHERE id = ?');
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Không thể cập nhật mã giảm giá.'];
            }
            $stmt->bind_param(
                'ssssdddiissii',
                $code,
                $name,
                $descriptionParam,
                $discountType,
                $discountValue,
                $minOrder,
                $maxDiscount,
                $totalLimit,
                $perCustomerLimit,
                $startsAt,
                $endsAt,
                $isActive,
                $id
            );
        }
        $ok = $stmt->execute();
        $stmt->close();

        return $ok
            ? ['ok' => true, 'message' => 'Đã cập nhật mã giảm giá.', 'coupon_id' => $id]
            : ['ok' => false, 'message' => 'Không thể cập nhật mã giảm giá.'];
    }

    if ($hasRole) {
        $stmt = $conn->prepare('INSERT INTO coupons
            (code, name, description, discount_type, coupon_role, discount_value,
             min_order_amount, max_discount_amount, total_usage_limit, per_customer_limit,
             starts_at, ends_at, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Không thể tạo mã giảm giá.'];
        }
        $stmt->bind_param(
            'sssssdddiissi',
            $code,
            $name,
            $descriptionParam,
            $discountType,
            $couponRole,
            $discountValue,
            $minOrder,
            $maxDiscount,
            $totalLimit,
            $perCustomerLimit,
            $startsAt,
            $endsAt,
            $isActive
        );
    } else {
        $stmt = $conn->prepare('INSERT INTO coupons
            (code, name, description, discount_type, discount_value,
             min_order_amount, max_discount_amount, total_usage_limit, per_customer_limit,
             starts_at, ends_at, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Không thể tạo mã giảm giá.'];
        }
        $stmt->bind_param(
            'ssssdddiissi',
            $code,
            $name,
            $descriptionParam,
            $discountType,
            $discountValue,
            $minOrder,
            $maxDiscount,
            $totalLimit,
            $perCustomerLimit,
            $startsAt,
            $endsAt,
            $isActive
        );
    }

    $ok = $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();

    return $ok
        ? ['ok' => true, 'message' => 'Đã tạo mã giảm giá mới.', 'coupon_id' => $newId]
        : ['ok' => false, 'message' => 'Không thể tạo mã giảm giá.'];
}

function couponAdminParseDatetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $ts);
}

function couponAdminToggleActive(mysqli $conn, int $couponId): bool
{
    if ($couponId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('UPDATE coupons SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $couponId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function couponAdminFormatDatetimeLocal(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $ts);
}
