<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * @return list<array{
 *   key: string,
 *   label: string,
 *   count: int,
 *   url: string,
 *   nav: string,
 *   priority: string
 * }>
 */
function adminTasksFetchCounts(mysqli $conn): array
{
    require_once __DIR__ . '/return-repository.php';
    require_once __DIR__ . '/inventory-repository.php';

    $counts = [
        'returns_pending' => 0,
        'returns_accepted' => 0,
        'returns_goods_received' => 0,
        'orders_shipped' => 0,
        'orders_cod_unpaid' => 0,
        'inventory_low' => 0,
    ];

    $queries = [
        'returns_pending' => "SELECT COUNT(*) AS c FROM order_return_requests WHERE status = 'pending'",
        'returns_accepted' => "SELECT COUNT(*) AS c FROM order_return_requests WHERE status = 'accepted'",
        'returns_goods_received' => "SELECT COUNT(*) AS c FROM order_return_requests WHERE status = 'goods_received'",
        'orders_shipped' => "SELECT COUNT(*) AS c FROM orders WHERE status = 'shipped'",
        'orders_cod_unpaid' => "SELECT COUNT(*) AS c FROM orders WHERE status = 'delivered' AND payment_status = 'unpaid'",
    ];

    returnEnsureSchema($conn);

    foreach ($queries as $key => $sql) {
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            $counts[$key] = (int) ($row['c'] ?? 0);
        }
    }

    $counts['inventory_low'] = inventoryCountUnreadAlerts($conn);

    $definitions = [
        'returns_pending' => [
            'label' => 'yêu cầu hoàn hàng chờ duyệt',
            'url' => app_url('admin-returns', ['status' => 'pending']),
            'nav' => 'admin-returns',
            'priority' => 'high',
        ],
        'returns_accepted' => [
            'label' => 'hoàn hàng chờ nhận hàng tại kho',
            'url' => app_url('admin-returns', ['status' => 'accepted']),
            'nav' => 'admin-returns',
            'priority' => 'medium',
        ],
        'returns_goods_received' => [
            'label' => 'hoàn hàng chờ chuyển khoản',
            'url' => app_url('admin-returns', ['status' => 'goods_received']),
            'nav' => 'admin-returns',
            'priority' => 'high',
        ],
        'orders_shipped' => [
            'label' => 'đơn đang giao cần cập nhật',
            'url' => app_url('admin-orders'),
            'nav' => 'admin-orders',
            'priority' => 'medium',
        ],
        'orders_cod_unpaid' => [
            'label' => 'đơn COD đã giao, chưa xác nhận thu tiền',
            'url' => app_url('admin-orders'),
            'nav' => 'admin-orders',
            'priority' => 'high',
        ],
        'inventory_low' => [
            'label' => 'cảnh báo tồn kho thấp',
            'url' => app_url('admin-products'),
            'nav' => 'admin-products',
            'priority' => 'medium',
        ],
    ];

    $tasks = [];
    foreach ($definitions as $key => $meta) {
        $count = $counts[$key] ?? 0;
        if ($count <= 0) {
            continue;
        }
        $tasks[] = [
            'key' => $key,
            'label' => $meta['label'],
            'count' => $count,
            'url' => $meta['url'],
            'nav' => $meta['nav'],
            'priority' => $meta['priority'],
        ];
    }

    return $tasks;
}

function adminTasksEnsureSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['admin_tasks_seen']) || !is_array($_SESSION['admin_tasks_seen'])) {
        $_SESSION['admin_tasks_seen'] = [];
    }
}

/** @param list<array{key:string,count:int,...}> $tasks */
function adminTasksFingerprint(array $tasks): string
{
    $parts = [];
    foreach ($tasks as $task) {
        $parts[] = $task['key'] . ':' . $task['count'];
    }
    sort($parts);

    return hash('sha256', implode('|', $parts));
}

/** @param list<array{key:string,count:int,...}> $tasks */
function adminTasksApplyReadState(array $tasks): array
{
    adminTasksEnsureSession();
    $seen = $_SESSION['admin_tasks_seen'];

    foreach ($tasks as &$task) {
        $key = $task['key'];
        $seenCount = (int) ($seen[$key] ?? -1);
        $task['is_unread'] = $task['count'] > $seenCount;
        $task['unread_delta'] = max(0, $task['count'] - max(0, $seenCount));
    }
    unset($task);

    return $tasks;
}

function adminTasksMarkSeen(string $taskKey, int $count): void
{
    adminTasksEnsureSession();
    $_SESSION['admin_tasks_seen'][$taskKey] = max((int) ($_SESSION['admin_tasks_seen'][$taskKey] ?? 0), $count);
}

/** @param list<array{key:string,count:int,...}> $tasks */
function adminTasksMarkAllSeen(array $tasks): void
{
    foreach ($tasks as $task) {
        adminTasksMarkSeen($task['key'], $task['count']);
    }
}

function adminTasksDismissBanner(string $fingerprint): void
{
    adminTasksEnsureSession();
    $_SESSION['admin_tasks_banner_fp'] = $fingerprint;
}

/** @param list<array{key:string,count:int,is_unread:bool,...}> $tasks */
function adminTasksShouldShowBanner(array $tasks): bool
{
    adminTasksEnsureSession();

    $unread = array_filter($tasks, static fn(array $t): bool => !empty($t['is_unread']));
    if ($unread === []) {
        return false;
    }

    $fp = adminTasksFingerprint($tasks);
    $dismissed = (string) ($_SESSION['admin_tasks_banner_fp'] ?? '');

    return $dismissed !== $fp;
}

/** @param list<array{key:string,count:int,is_unread:bool,nav:string,...}> $tasks */
function adminTasksUnreadTotal(array $tasks): int
{
    $total = 0;
    foreach ($tasks as $task) {
        if (!empty($task['is_unread'])) {
            $total += (int) $task['unread_delta'];
        }
    }

    return $total;
}

/** @param list<array{nav:string,is_unread:bool,unread_delta:int,...}> $tasks */
function adminTasksNavBadges(array $tasks): array
{
    $badges = [
        'admin-orders' => 0,
        'admin-returns' => 0,
        'admin-products' => 0,
    ];

    foreach ($tasks as $task) {
        if (empty($task['is_unread'])) {
            continue;
        }
        $nav = (string) ($task['nav'] ?? '');
        if (!isset($badges[$nav])) {
            continue;
        }
        $badges[$nav] += (int) $task['unread_delta'];
    }

    return $badges;
}

function adminTasksAutoMarkForView(string $view, mysqli $conn): void
{
    $map = [
        'admin-returns' => ['returns_pending', 'returns_accepted', 'returns_goods_received'],
        'admin-orders' => ['orders_shipped', 'orders_cod_unpaid'],
        'admin-order-detail' => ['orders_shipped', 'orders_cod_unpaid'],
        'admin-products' => ['inventory_low'],
        'admin-product-create' => ['inventory_low'],
        'admin-product-edit' => ['inventory_low'],
    ];

    if (!isset($map[$view])) {
        return;
    }

    $tasks = adminTasksFetchCounts($conn);
    foreach ($tasks as $task) {
        if (in_array($task['key'], $map[$view], true)) {
            adminTasksMarkSeen($task['key'], $task['count']);
        }
    }
}

/** @return array{
 *   tasks: list<array>,
 *   unread_total: int,
 *   badges: array<string,int>,
 *   banner_visible: bool,
 *   fingerprint: string
 * } */
function adminTasksGetPayload(mysqli $conn): array
{
    $tasks = adminTasksFetchCounts($conn);
    $tasks = adminTasksApplyReadState($tasks);
    $fingerprint = adminTasksFingerprint($tasks);

    return [
        'tasks' => $tasks,
        'unread_total' => adminTasksUnreadTotal($tasks),
        'badges' => adminTasksNavBadges($tasks),
        'banner_visible' => adminTasksShouldShowBanner($tasks),
        'fingerprint' => $fingerprint,
    ];
}

function adminTasksPriorityClass(string $priority): string
{
    return match ($priority) {
        'high' => 'admin-task-priority--high',
        'medium' => 'admin-task-priority--medium',
        default => 'admin-task-priority--low',
    };
}
