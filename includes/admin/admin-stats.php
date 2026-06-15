<?php
require_once __DIR__ . '/../../config/database.php';

function adminGetDashboardStats(mysqli $conn): array
{
    $stats = [
        'orders_total' => 0,
        'orders_pending' => 0,
        'products_total' => 0,
        'products_active' => 0,
        'customers_total' => 0,
        'reviews_total' => 0,
        'revenue_total' => 0.0,
        'revenue_net' => 0.0,
        'revenue_refunded' => 0.0,
        'returns_pending' => 0,
        'inventory_alerts_unread' => 0,
    ];

    $queries = [
        'orders_total' => "SELECT COUNT(*) AS c FROM orders",
        'orders_pending' => "SELECT COUNT(*) AS c FROM orders WHERE status = 'shipped'",
        'products_total' => "SELECT COUNT(*) AS c FROM products",
        'products_active' => "SELECT COUNT(*) AS c FROM products WHERE is_active = 1",
        'customers_total' => "SELECT COUNT(*) AS c FROM customers",
        'reviews_total' => "SELECT COUNT(*) AS c FROM product_reviews WHERE is_admin_reply = 0 AND (parent_id IS NULL OR parent_id = 0)",
        'revenue_total' => "SELECT COALESCE(SUM(grand_total), 0) AS c FROM orders WHERE status NOT IN ('cancelled', 'returned')",
        'revenue_net' => "SELECT COALESCE(SUM(grand_total), 0) AS c FROM orders WHERE status = 'delivered' AND payment_status = 'paid'",
        'revenue_refunded' => "SELECT COALESCE(SUM(grand_total), 0) AS c FROM orders WHERE status = 'returned'",
    ];

    foreach ($queries as $key => $sql) {
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            $stats[$key] = str_starts_with($key, 'revenue_') ? (float) ($row['c'] ?? 0) : (int) ($row['c'] ?? 0);
        }
    }

    require_once __DIR__ . '/../repositories/inventory-repository.php';
    $stats['inventory_alerts_unread'] = inventoryCountUnreadAlerts($conn);

    require_once __DIR__ . '/../repositories/return-repository.php';
    $stats['returns_pending'] = returnCountPending($conn);

    return $stats;
}

function adminGetRecentOrders(mysqli $conn, int $limit = 5): array
{
    $stmt = $conn->prepare("SELECT order_code, customer_name, grand_total, status, ordered_at
                            FROM orders ORDER BY id DESC LIMIT ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $limit);
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
 * @return array{
 *   period: string,
 *   group: string,
 *   start: DateTimeImmutable,
 *   end: DateTimeImmutable,
 *   label: string,
 *   revenue_month: string,
 *   revenue_date: string,
 *   revenue_year: string,
 *   revenue_from: string,
 *   revenue_to: string
 * }
 */
function adminParseRevenueFilter(array $get): array
{
    $period = trim((string) ($get['revenue_period'] ?? 'month'));
    if (!in_array($period, ['day', 'month', 'year', 'range'], true)) {
        $period = 'month';
    }

    $today = new DateTimeImmutable('today');

    if ($period === 'day') {
        $dateStr = trim((string) ($get['revenue_date'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if (!$date) {
            $date = $today;
        }
        $dateStr = $date->format('Y-m-d');

        return [
            'period' => 'day',
            'group' => 'hour',
            'start' => $date->setTime(0, 0, 0),
            'end' => $date->modify('+1 day')->setTime(0, 0, 0),
            'label' => 'Ngày ' . $date->format('d/m/Y'),
            'revenue_month' => $today->format('Y-m'),
            'revenue_date' => $dateStr,
            'revenue_year' => $date->format('Y'),
            'revenue_from' => $dateStr,
            'revenue_to' => $dateStr,
        ];
    }

    if ($period === 'year') {
        $year = (int) ($get['revenue_year'] ?? (int) $today->format('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) $today->format('Y');
        }

        return [
            'period' => 'year',
            'group' => 'month',
            'start' => new DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year)),
            'end' => new DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year + 1)),
            'label' => 'Năm ' . $year,
            'revenue_month' => $today->format('Y-m'),
            'revenue_date' => $today->format('Y-m-d'),
            'revenue_year' => (string) $year,
            'revenue_from' => sprintf('%04d-01-01', $year),
            'revenue_to' => sprintf('%04d-12-31', $year),
        ];
    }

    if ($period === 'range') {
        $fromStr = trim((string) ($get['revenue_from'] ?? ''));
        $toStr = trim((string) ($get['revenue_to'] ?? ''));
        $from = DateTimeImmutable::createFromFormat('Y-m-d', $fromStr);
        $to = DateTimeImmutable::createFromFormat('Y-m-d', $toStr);

        if (!$from) {
            $from = $today->modify('-29 days');
        }
        if (!$to) {
            $to = $today;
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $days = (int) $from->diff($to)->days + 1;
        $group = $days > 62 ? 'month' : 'day';

        return [
            'period' => 'range',
            'group' => $group,
            'start' => $from->setTime(0, 0, 0),
            'end' => $to->modify('+1 day')->setTime(0, 0, 0),
            'label' => $from->format('d/m/Y') . ' – ' . $to->format('d/m/Y'),
            'revenue_month' => $today->format('Y-m'),
            'revenue_date' => $today->format('Y-m-d'),
            'revenue_year' => $today->format('Y'),
            'revenue_from' => $from->format('Y-m-d'),
            'revenue_to' => $to->format('Y-m-d'),
        ];
    }

    $monthStr = trim((string) ($get['revenue_month'] ?? ''));
    $month = DateTimeImmutable::createFromFormat('Y-m-d', $monthStr . '-01');
    if (!$month) {
        $month = $today->modify('first day of this month');
    }

    return [
        'period' => 'month',
        'group' => 'day',
        'start' => $month->setTime(0, 0, 0),
        'end' => $month->modify('first day of next month')->setTime(0, 0, 0),
        'label' => 'Tháng ' . $month->format('m/Y'),
        'revenue_month' => $month->format('Y-m'),
        'revenue_date' => $today->format('Y-m-d'),
        'revenue_year' => $month->format('Y'),
        'revenue_from' => $month->format('Y-m-d'),
        'revenue_to' => $month->modify('last day of this month')->format('Y-m-d'),
    ];
}

/**
 * @return array{net: float, refunded: float, orders: int}
 */
function adminGetRevenuePeriodSummary(mysqli $conn, array $filter): array
{
    $start = $filter['start']->format('Y-m-d H:i:s');
    $end = $filter['end']->format('Y-m-d H:i:s');

    $sql = "SELECT
                COALESCE(SUM(CASE WHEN status = 'delivered' AND payment_status = 'paid' THEN grand_total ELSE 0 END), 0) AS net,
                COALESCE(SUM(CASE WHEN status = 'returned' THEN grand_total ELSE 0 END), 0) AS refunded,
                COUNT(*) AS orders
            FROM orders
            WHERE ordered_at >= ? AND ordered_at < ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['net' => 0.0, 'refunded' => 0.0, 'orders' => 0];
    }

    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    return [
        'net' => (float) ($row['net'] ?? 0),
        'refunded' => (float) ($row['refunded'] ?? 0),
        'orders' => (int) ($row['orders'] ?? 0),
    ];
}

/**
 * @return array{
 *   labels: list<string>,
 *   net: list<float>,
 *   refunded: list<float>
 * }
 */
function adminGetRevenueChartData(mysqli $conn, array $filter): array
{
    $start = $filter['start']->format('Y-m-d H:i:s');
    $end = $filter['end']->format('Y-m-d H:i:s');
    $group = $filter['group'];

    if ($group === 'hour') {
        $sql = "SELECT HOUR(ordered_at) AS bucket,
                       COALESCE(SUM(CASE WHEN status = 'delivered' AND payment_status = 'paid' THEN grand_total ELSE 0 END), 0) AS net,
                       COALESCE(SUM(CASE WHEN status = 'returned' THEN grand_total ELSE 0 END), 0) AS refunded
                FROM orders
                WHERE ordered_at >= ? AND ordered_at < ?
                GROUP BY HOUR(ordered_at)
                ORDER BY bucket";
    } elseif ($group === 'month') {
        $sql = "SELECT DATE_FORMAT(ordered_at, '%Y-%m') AS bucket,
                       COALESCE(SUM(CASE WHEN status = 'delivered' AND payment_status = 'paid' THEN grand_total ELSE 0 END), 0) AS net,
                       COALESCE(SUM(CASE WHEN status = 'returned' THEN grand_total ELSE 0 END), 0) AS refunded
                FROM orders
                WHERE ordered_at >= ? AND ordered_at < ?
                GROUP BY DATE_FORMAT(ordered_at, '%Y-%m')
                ORDER BY bucket";
    } else {
        $sql = "SELECT DATE(ordered_at) AS bucket,
                       COALESCE(SUM(CASE WHEN status = 'delivered' AND payment_status = 'paid' THEN grand_total ELSE 0 END), 0) AS net,
                       COALESCE(SUM(CASE WHEN status = 'returned' THEN grand_total ELSE 0 END), 0) AS refunded
                FROM orders
                WHERE ordered_at >= ? AND ordered_at < ?
                GROUP BY DATE(ordered_at)
                ORDER BY bucket";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['labels' => [], 'net' => [], 'refunded' => []];
    }

    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();

    $byBucket = [];
    while ($row = $result->fetch_assoc()) {
        $key = (string) $row['bucket'];
        $byBucket[$key] = [
            'net' => (float) $row['net'],
            'refunded' => (float) $row['refunded'],
        ];
    }
    $stmt->close();

    return adminBuildRevenueChartSeries($filter, $byBucket);
}

/**
 * @param array<string, array{net: float, refunded: float}> $byBucket
 * @return array{labels: list<string>, net: list<float>, refunded: list<float>}
 */
function adminBuildRevenueChartSeries(array $filter, array $byBucket): array
{
    $labels = [];
    $net = [];
    $refunded = [];
    $group = $filter['group'];
    $start = $filter['start'];
    $end = $filter['end'];

    if ($group === 'hour') {
        for ($hour = 0; $hour < 24; $hour++) {
            $key = (string) $hour;
            $labels[] = sprintf('%02dh', $hour);
            $net[] = $byBucket[$key]['net'] ?? 0.0;
            $refunded[] = $byBucket[$key]['refunded'] ?? 0.0;
        }
        return compact('labels', 'net', 'refunded');
    }

    if ($group === 'month') {
        $cursor = $start;
        while ($cursor < $end) {
            $key = $cursor->format('Y-m');
            $labels[] = 'T' . $cursor->format('n') . '/' . $cursor->format('Y');
            $net[] = $byBucket[$key]['net'] ?? 0.0;
            $refunded[] = $byBucket[$key]['refunded'] ?? 0.0;
            $cursor = $cursor->modify('first day of next month');
        }
        return compact('labels', 'net', 'refunded');
    }

    $cursor = $start;
    while ($cursor < $end) {
        $key = $cursor->format('Y-m-d');
        $labels[] = $cursor->format('d/m');
        $net[] = $byBucket[$key]['net'] ?? 0.0;
        $refunded[] = $byBucket[$key]['refunded'] ?? 0.0;
        $cursor = $cursor->modify('+1 day');
    }

    return compact('labels', 'net', 'refunded');
}
