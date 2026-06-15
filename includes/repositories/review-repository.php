<?php
require_once __DIR__ . '/../../config/database.php';

function reviewMapRow(array $row): array
{
    $createdAt = (string) ($row['created_at'] ?? '');
    $label = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '';

    return [
        'id' => (int) $row['id'],
        'product_id' => (int) $row['product_id'],
        'product_name' => $row['product_name'] ?? '',
        'product_slug' => $row['product_slug'] ?? '',
        'parent_id' => isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
        'parent_reviewer_name' => $row['parent_reviewer_name'] ?? '',
        'is_admin_reply' => !empty($row['is_admin_reply']),
        'reviewer_name' => $row['reviewer_name'],
        'reviewer_email' => $row['reviewer_email'] ?? '',
        'rating' => (int) $row['rating'],
        'title' => $row['title'] ?? '',
        'content' => $row['content'] ?? '',
        'status' => $row['status'],
        'created_at' => $createdAt,
        'created_label' => $label,
        'replies' => [],
    ];
}

function reviewNestApproved(array $flatReviews): array
{
    $repliesByParent = [];
    $roots = [];

    foreach ($flatReviews as $review) {
        $review['replies'] = [];
        if ($review['parent_id'] === null || (int) $review['parent_id'] === 0) {
            $roots[$review['id']] = $review;
        } else {
            $repliesByParent[$review['parent_id']][] = $review;
        }
    }

    $attach = static function (array $node) use (&$attach, $repliesByParent): array {
        $children = $repliesByParent[$node['id']] ?? [];
        $node['replies'] = array_map(static fn (array $child) => $attach($child), $children);

        return $node;
    };

    return array_values(array_map($attach, $roots));
}

function reviewGetApprovedByProduct(mysqli $conn, int $productId, int $limit = 20): array
{
    $stmt = $conn->prepare("SELECT id, product_id, parent_id, is_admin_reply, reviewer_name, reviewer_email, rating, title, content, status, created_at
                            FROM product_reviews
                            WHERE product_id = ?
                              AND status = 'approved'
                            ORDER BY COALESCE(parent_id, id) DESC, id ASC
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

    return reviewNestApproved($items);
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

    $extraCols = [
        'parent_id' => 'BIGINT DEFAULT NULL',
        'is_admin_reply' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ];
    foreach ($extraCols as $col => $def) {
        $check = $conn->query("SHOW COLUMNS FROM product_reviews LIKE '" . $conn->real_escape_string($col) . "'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE product_reviews ADD COLUMN {$col} {$def}");
        }
        if ($check) {
            $check->free();
        }
    }

    $indexCheck = $conn->query("SHOW INDEX FROM product_reviews WHERE Key_name = 'idx_product_reviews_parent'");
    if ($indexCheck && $indexCheck->num_rows === 0) {
        $conn->query('ALTER TABLE product_reviews ADD INDEX idx_product_reviews_parent (parent_id)');
    }
    if ($indexCheck) {
        $indexCheck->free();
    }

    $result = $conn->query("SELECT DISTINCT product_id FROM product_reviews WHERE status = 'pending' AND (parent_id IS NULL OR parent_id = 0) AND is_admin_reply = 0");
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
                              AND status = 'approved'
                              AND is_admin_reply = 0
                              AND (parent_id IS NULL OR parent_id = 0)");
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
    $sql = "SELECT r.id, r.product_id, r.parent_id, r.is_admin_reply, r.reviewer_name, r.reviewer_email, r.rating, r.title, r.content, r.status, r.created_at,
                   p.name AS product_name, p.slug AS product_slug,
                   parent.reviewer_name AS parent_reviewer_name
            FROM product_reviews r
            JOIN products p ON p.id = r.product_id
            LEFT JOIN product_reviews parent ON parent.id = r.parent_id";
    if ($statusFilter !== null && $statusFilter !== '') {
        $sql .= " WHERE r.status = ?";
    }
    $sql .= " ORDER BY COALESCE(r.parent_id, r.id) DESC, r.id ASC LIMIT ?";

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

function reviewAdminGetNested(mysqli $conn, int $limit = 100): array
{
    return reviewNestApproved(reviewAdminGetAll($conn, null, $limit));
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

    $stmtChildren = $conn->prepare('DELETE FROM product_reviews WHERE parent_id = ?');
    if ($stmtChildren) {
        $stmtChildren->bind_param('i', $reviewId);
        $stmtChildren->execute();
        $stmtChildren->close();
    }

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

/**
 * Admin trả lời đánh giá khách — tự duyệt và hiển thị ngay trên trang sản phẩm.
 *
 * @return array{ok:bool,message:string}
 */
function reviewAdminReply(mysqli $conn, int $parentId, string $content): array
{
    $content = trim($content);
    if ($parentId <= 0) {
        return ['ok' => false, 'message' => 'Đánh giá không hợp lệ.'];
    }
    if ($content === '') {
        return ['ok' => false, 'message' => 'Vui lòng nhập nội dung trả lời.'];
    }
    if (mb_strlen($content) > 2000) {
        return ['ok' => false, 'message' => 'Nội dung trả lời không được vượt quá 2000 ký tự.'];
    }

    $stmtParent = $conn->prepare("SELECT id, product_id, parent_id, status, is_admin_reply FROM product_reviews WHERE id = ? LIMIT 1");
    if (!$stmtParent) {
        return ['ok' => false, 'message' => 'Không thể trả lời đánh giá.'];
    }
    $stmtParent->bind_param('i', $parentId);
    $stmtParent->execute();
    $parent = $stmtParent->get_result()->fetch_assoc();
    $stmtParent->close();

    if (!$parent) {
        return ['ok' => false, 'message' => 'Không tìm thấy đánh giá.'];
    }
    if (!empty($parent['is_admin_reply'])) {
        return ['ok' => false, 'message' => 'Chỉ trả lời được đánh giá của khách.'];
    }

    $productId = (int) $parent['product_id'];
    $reviewerName = 'Winsum Home';
    $rating = 0;
    $title = '';
    $isAdminReply = 1;
    $status = 'approved';

    $stmt = $conn->prepare("INSERT INTO product_reviews
        (product_id, customer_id, parent_id, is_admin_reply, reviewer_name, reviewer_email, rating, title, content, status)
        VALUES (?, NULL, ?, ?, ?, NULL, ?, ?, ?, ?)");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Không thể lưu câu trả lời.'];
    }
    $stmt->bind_param('iiisisss', $productId, $parentId, $isAdminReply, $reviewerName, $rating, $title, $content, $status);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'message' => 'Không thể lưu câu trả lời.'];
    }

    return ['ok' => true, 'message' => 'Đã gửi trả lời. Khách sẽ thấy ngay trên trang sản phẩm.'];
}

function reviewAdminGetRecent(mysqli $conn, int $limit = 5): array
{
    $stmt = $conn->prepare("SELECT r.id, r.product_id, r.reviewer_name, r.rating, r.title, r.content, r.status, r.created_at,
                                   p.name AS product_name, p.slug AS product_slug
                            FROM product_reviews r
                            JOIN products p ON p.id = r.product_id
                            WHERE r.is_admin_reply = 0
                              AND (r.parent_id IS NULL OR r.parent_id = 0)
                            ORDER BY r.id DESC
                            LIMIT ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = reviewMapRow($row);
    }
    $stmt->close();

    return $items;
}
