<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product-repository.php';

$query = trim((string) ($_GET['q'] ?? ''));
$limit = max(1, min(12, (int) ($_GET['limit'] ?? 8)));

try {
    $payload = productSearchAjax($conn, $query, $limit);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Không thể tìm kiếm lúc này.',
        'query' => $query,
        'suggestions' => [],
        'products' => [],
        'total' => 0,
    ], JSON_UNESCAPED_UNICODE);
}
