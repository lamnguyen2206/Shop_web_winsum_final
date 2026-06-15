<?php

declare(strict_types=1);

/**
 * @return array{q:string,status:string,role:string,page:int}
 */
function customerAdminParseFilters(array $source): array
{
    $status = trim((string) ($source['status'] ?? ''));
    if (!in_array($status, ['active', 'inactive', 'blocked'], true)) {
        $status = '';
    }

    $role = trim((string) ($source['role'] ?? 'customer'));
    if (!in_array($role, ['customer', 'admin', 'all'], true)) {
        $role = 'customer';
    }

    return [
        'q' => trim((string) ($source['q'] ?? '')),
        'status' => $status,
        'role' => $role,
        'page' => max(1, (int) ($source['page'] ?? 1)),
    ];
}

function customerAdminBuildListUrl(array $filters, int $page = 1): string
{
    $params = ['view' => 'admin-customers', 'page' => $page];
    if ($filters['q'] !== '') {
        $params['q'] = $filters['q'];
    }
    if ($filters['status'] !== '') {
        $params['status'] = $filters['status'];
    }
    if (($filters['role'] ?? 'customer') !== 'customer') {
        $params['role'] = $filters['role'];
    }
    return 'index.php?' . http_build_query($params);
}

function customerAdminCount(mysqli $conn, array $filters): int
{
    [$where, $types, $values] = customerAdminBuildWhere($filters);
    $sql = 'SELECT COUNT(*) AS c FROM customers c' . $where;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['c'] ?? 0);
}

function customerAdminList(mysqli $conn, array $filters, int $limit = 20, int $offset = 0): array
{
    [$where, $types, $values] = customerAdminBuildWhere($filters);
    $sql = "SELECT c.id, c.customer_code, c.full_name, c.phone, c.email, c.status, c.role,
                   c.created_at,
                   (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,
                   (SELECT COALESCE(SUM(o.grand_total), 0) FROM orders o
                    WHERE o.customer_id = c.id AND o.status = 'delivered' AND o.payment_status = 'paid') AS total_spent
            FROM customers c
            {$where}
            ORDER BY c.id DESC
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $types .= 'ii';
    $values[] = $limit;
    $values[] = $offset;
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/**
 * @return array{0:string,1:string,2:array<int, mixed>}
 */
function customerAdminBuildWhere(array $filters): array
{
    $parts = [];
    $types = '';
    $values = [];

    if (($filters['role'] ?? '') !== '' && $filters['role'] !== 'all') {
        $parts[] = 'c.role = ?';
        $types .= 's';
        $values[] = $filters['role'];
    }

    if (($filters['status'] ?? '') !== '') {
        $parts[] = 'c.status = ?';
        $types .= 's';
        $values[] = $filters['status'];
    }

    if (($filters['q'] ?? '') !== '') {
        $like = '%' . $filters['q'] . '%';
        $parts[] = '(c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.customer_code LIKE ?)';
        $types .= 'ssss';
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
    }

    $where = $parts !== [] ? ' WHERE ' . implode(' AND ', $parts) : '';
    return [$where, $types, $values];
}

function customerAdminGetById(mysqli $conn, int $customerId): ?array
{
    if ($customerId <= 0) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT c.id, c.customer_code, c.full_name, c.phone, c.email, c.status, c.role,
                c.created_at,
                (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,
                (SELECT COALESCE(SUM(o.grand_total), 0) FROM orders o
                 WHERE o.customer_id = c.id AND o.status = 'delivered' AND o.payment_status = 'paid') AS total_spent
         FROM customers c
         WHERE c.id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function customerAdminGetRecentOrders(mysqli $conn, int $customerId, int $limit = 10): array
{
    if ($customerId <= 0) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT id, order_code, grand_total, status, ordered_at
         FROM orders
         WHERE customer_id = ?
         ORDER BY id DESC
         LIMIT ?"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $customerId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function customerAdminUpdateStatus(mysqli $conn, int $customerId, string $status, int $actingCustomerId = 0): array
{
    if (!in_array($status, ['active', 'inactive', 'blocked'], true)) {
        return ['ok' => false, 'message' => 'Trạng thái không hợp lệ.'];
    }

    $customer = customerAdminGetById($conn, $customerId);
    if (!$customer) {
        return ['ok' => false, 'message' => 'Không tìm thấy khách hàng.'];
    }

    if ($actingCustomerId > 0 && $actingCustomerId === $customerId) {
        return ['ok' => false, 'message' => 'Bạn không thể đổi trạng thái tài khoản đang đăng nhập.'];
    }

    if (($customer['role'] ?? '') === 'admin' && $status === 'blocked') {
        return ['ok' => false, 'message' => 'Không thể khóa tài khoản quản trị.'];
    }

    $stmt = $conn->prepare('UPDATE customers SET status = ? WHERE id = ?');
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Không thể cập nhật trạng thái.'];
    }
    $stmt->bind_param('si', $status, $customerId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'message' => 'Không thể cập nhật trạng thái.'];
    }

    $labels = [
        'active' => 'Hoạt động',
        'inactive' => 'Ngưng hoạt động',
        'blocked' => 'Đã khóa',
    ];

    return [
        'ok' => true,
        'message' => 'Đã đổi trạng thái thành: ' . ($labels[$status] ?? $status) . '.',
    ];
}

function customerAdminStatusLabel(string $status): string
{
    return match ($status) {
        'active' => 'Hoạt động',
        'inactive' => 'Ngưng',
        'blocked' => 'Đã khóa',
        default => $status,
    };
}

function customerAdminRoleLabel(string $role): string
{
    return $role === 'admin' ? 'Quản trị' : 'Khách hàng';
}

function customerAdminCanManageRow(array $customer, int $actingCustomerId = 0): bool
{
    if (($customer['role'] ?? '') === 'admin') {
        return false;
    }
    if ($actingCustomerId > 0 && $actingCustomerId === (int) ($customer['id'] ?? 0)) {
        return false;
    }
    return true;
}

function customerAdminToggleBlock(mysqli $conn, int $customerId, int $actingCustomerId = 0): array
{
    $customer = customerAdminGetById($conn, $customerId);
    if (!$customer) {
        return ['ok' => false, 'message' => 'Không tìm thấy khách hàng.'];
    }
    if (!customerAdminCanManageRow($customer, $actingCustomerId)) {
        return ['ok' => false, 'message' => 'Không thể thay đổi trạng thái tài khoản này.'];
    }

    $newStatus = ($customer['status'] ?? '') === 'blocked' ? 'active' : 'blocked';
    return customerAdminUpdateStatus($conn, $customerId, $newStatus, $actingCustomerId);
}

