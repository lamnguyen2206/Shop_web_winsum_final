<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-tasks.php';
require_once __DIR__ . '/../includes/csrf.php';

csrfToken();

if (!adminCurrent()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Cần đăng nhập quản trị.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if ($input === [] && str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    $action = (string) ($input['action'] ?? '');
    $csrf = (string) ($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

    if ($csrf === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Phiên làm việc không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'dismiss_banner') {
        $fingerprint = (string) ($input['fingerprint'] ?? '');
        if ($fingerprint !== '') {
            adminTasksDismissBanner($fingerprint);
        }
    } elseif ($action === 'mark_read') {
        $taskKey = (string) ($input['task_key'] ?? '');
        $taskCount = (int) ($input['task_count'] ?? 0);
        if ($taskKey !== '' && $taskCount >= 0) {
            adminTasksMarkSeen($taskKey, $taskCount);
        }
    } elseif ($action === 'mark_all_read') {
        $tasks = adminTasksFetchCounts($conn);
        adminTasksMarkAllSeen($tasks);
        $fingerprint = adminTasksFingerprint($tasks);
        adminTasksDismissBanner($fingerprint);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Thao tác không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$payload = adminTasksGetPayload($conn);
$payload['ok'] = true;
$payload['csrf_token'] = (string) ($_SESSION['csrf_token'] ?? '');

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
