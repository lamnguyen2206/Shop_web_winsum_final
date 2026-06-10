<?php
require_once __DIR__ . '/../config/database.php';

function reviewMapRow(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'product_id' => (int) $row['product_id'],
        'product_name' => $row['product_name'] ?? '',
        'reviewer_name' => $row['reviewer_name'],
        'reviewer_email' => $row['reviewer_email'] ?? '',
        'rating' => (int) $row['rating'],
        'title' => $row['title'] ?? '',
        'content' => $row['content'] ?? '',
        'status' => $row['status'],
        'created_at' => $row['created_at'],
    ];
}

function reviewGetApprovedByProduct(mysqli $conn, int $productId, int $limit = 20): array
{
    $stmt = $conn->prepare("SELECT id, product_id, reviewer_name, reviewer_email, rating, title, content, status, created_at
                            FROM product_reviews
                            WHERE product_id = ?
                              AND status = 'approved'
                            ORDER BY id DESC
                            LIMIT ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $productId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = reviewMapRow($row);
    }
    $stmt->close();
    return $items;
}

function reviewGetProductStats(mysqli $conn, int $productId): array
{
    $stmt = $conn->prepare("SELECT rating_average, rating_count FROM products WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return ['average' => 0, 'count' => 0];
    }
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return [
        'average' => round((float) ($row['rating_average'] ?? 0), 1),
        'count' => (int) ($row['rating_count'] ?? 0),
    ];
}

function reviewCustomerCanReviewProduct(mysqli $conn, int $customerId, int $productId): bool
{
    if ($customerId <= 0 || $productId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT 1
                            FROM orders o
                            INNER JOIN order_items oi ON oi.order_id = o.id
                            WHERE o.customer_id = ?
                              AND oi.product_id = ?
                              AND (o.status = 'delivered' OR o.fulfillment_status = 'delivered')
                            LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $customerId, $productId);
    $stmt->execute();
    $canReview = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $canReview;
}

function reviewEnsureSchema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $result = $conn->query("SELECT DISTINCT product_id FROM product_reviews WHERE status = 'pending'");
    if (!$result || $result->num_rows === 0) {
        if ($result) {
            $result->free();
        }
        return;
    }
    $productIds = [];
    while ($row = $result->fetch_assoc()) {
        $productIds[] = (int) $row['product_id'];
    }
    $result->free();

    $conn->query("UPDATE product_reviews SET status = 'approved' WHERE status = 'pending'");
    foreach ($productIds as $productId) {
        reviewRecalculateProductRating($conn, $productId);
    }
}

function reviewCustomerHasReviewedProduct(mysqli $conn, int $customerId, int $productId): bool
{
    if ($customerId <= 0 || $productId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT id
                            FROM product_reviews
                            WHERE customer_id = ?
                              AND product_id = ?
                              AND status = 'approved'
                            LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $customerId, $productId);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $exists;
}

function reviewRecalculateProductRating(mysqli $conn, int $productId): void
{
    $stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total
                            FROM product_reviews
                            WHERE product_id = ?
                              AND status = 'approved'");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $avg = round((float) ($row['avg_rating'] ?? 0), 2);
    $count = (int) ($row['total'] ?? 0);

    $stmtUpdate = $conn->prepare("UPDATE products SET rating_average = ?, rating_count = ? WHERE id = ?");
    if ($stmtUpdate) {
        $stmtUpdate->bind_param('dii', $avg, $count, $productId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
}

function reviewCreate(mysqli $conn, int $productId, string $name, string $email, int $rating, string $title, string $content, ?int $customerId = null): array
{
    $name = trim($name);
    $email = trim($email);
    $title = trim($title);
    $content = trim($content);
    $rating = max(1, min(5, $rating));

    if ($name === '' || $content === '') {
        return ['ok' => false, 'message' => 'Vui lòng nhập họ tên và nội dung đánh giá.'];
    }
    if (!$customerId || $customerId <= 0) {
        return ['ok' => false, 'message' => 'Bạn cần đăng nhập và mua sản phẩm này trước khi đánh giá.'];
    }
    $canReviewProduct = reviewCustomerCanReviewProduct($conn, $customerId, $productId);
    $hasReviewedProduct = reviewCustomerHasReviewedProduct($conn, $customerId, $productId);
    if (!$canReviewProduct) {
        return ['ok' => false, 'message' => 'Bạn chỉ được đánh giá sản phẩm trong đơn hàng đã giao thành công.'];
    }
    if ($hasReviewedProduct) {
        return ['ok' => false, 'message' => 'Bạn đã đánh giá sản phẩm này rồi.'];
    }

    $emailParam = $email !== '' ? $email : null;
    $titleParam = $title !== '' ? $title : '';
    $customerIdValue = $customerId && $customerId > 0 ? $customerId : null;
    $status = 'approved';

    $stmt = $conn->prepare("INSERT INTO product_reviews
        (product_id, customer_id, reviewer_name, reviewer_email, rating, title, content, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Không thể gửi đánh giá.'];
    }
    $stmt->bind_param('iississs', $productId, $customerIdValue, $name, $emailParam, $rating, $titleParam, $content, $status);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'message' => 'Không thể lưu đánh giá.'];
    }

    reviewRecalculateProductRating($conn, $productId);

    return ['ok' => true, 'message' => 'Cảm ơn bạn! Đánh giá đã được đăng.'];
}

function reviewAdminGetAll(mysqli $conn, ?string $statusFilter = null, int $limit = 100): array
{
    $sql = "SELECT r.id, r.product_id, r.reviewer_name, r.reviewer_email, r.rating, r.title, r.content, r.status, r.created_at,
                   p.name AS product_name
            FROM product_reviews r
            JOIN products p ON p.id = r.product_id";
    if ($statusFilter !== null && $statusFilter !== '') {
        $sql .= " WHERE r.status = ?";
    }
    $sql .= " ORDER BY r.id DESC LIMIT ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($statusFilter !== null && $statusFilter !== '') {
        $stmt->bind_param('si', $statusFilter, $limit);
    } else {
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = reviewMapRow($row);
    }
    $stmt->close();
    return $items;
}

function reviewAdminUpdateStatus(mysqli $conn, int $reviewId, string $status): bool
{
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        return false;
    }

    $stmtGet = $conn->prepare("SELECT product_id FROM product_reviews WHERE id = ? LIMIT 1");
    if (!$stmtGet) {
        return false;
    }
    $stmtGet->bind_param('i', $reviewId);
    $stmtGet->execute();
    $row = $stmtGet->get_result()->fetch_assoc();
    $stmtGet->close();
    if (!$row) {
        return false;
    }
    $productId = (int) $row['product_id'];

    $stmt = $conn->prepare("UPDATE product_reviews SET status = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $status, $reviewId);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        reviewRecalculateProductRating($conn, $productId);
    }
    return $ok;
}

function reviewAdminDelete(mysqli $conn, int $reviewId): bool
{
    $stmtGet = $conn->prepare("SELECT product_id FROM product_reviews WHERE id = ? LIMIT 1");
    if (!$stmtGet) {
        return false;
    }
    $stmtGet->bind_param('i', $reviewId);
    $stmtGet->execute();
    $row = $stmtGet->get_result()->fetch_assoc();
    $stmtGet->close();
    if (!$row) {
        return false;
    }
    $productId = (int) $row['product_id'];

    $stmt = $conn->prepare("DELETE FROM product_reviews WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $reviewId);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        reviewRecalculateProductRating($conn, $productId);
    }
    return $ok;
}
