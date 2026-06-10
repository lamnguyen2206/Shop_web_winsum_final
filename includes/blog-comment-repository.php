<?php

declare(strict_types=1);

function blogCommentEnsureTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS blog_comments (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        post_id BIGINT NOT NULL,
        customer_id BIGINT DEFAULT NULL,
        author_name VARCHAR(120) NOT NULL,
        author_email VARCHAR(120) DEFAULT NULL,
        content TEXT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_blog_comments_post (post_id),
        INDEX idx_blog_comments_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function blogCommentMapRow(array $row): array
{
    $createdAt = (string) ($row['created_at'] ?? '');
    $label = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '';

    return [
        'id' => (int) $row['id'],
        'post_id' => (int) $row['post_id'],
        'post_title' => $row['post_title'] ?? '',
        'post_slug' => $row['post_slug'] ?? '',
        'author_name' => $row['author_name'],
        'author_email' => $row['author_email'] ?? '',
        'content' => $row['content'],
        'status' => $row['status'],
        'created_at' => $createdAt,
        'created_label' => $label,
    ];
}

function blogCommentGetApprovedByPost(mysqli $conn, int $postId, int $limit = 50): array
{
    $stmt = $conn->prepare("SELECT id, post_id, author_name, author_email, content, status, created_at
                            FROM blog_comments
                            WHERE post_id = ?
                              AND status = 'approved'
                            ORDER BY id ASC
                            LIMIT ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $postId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = blogCommentMapRow($row);
    }
    $stmt->close();
    return $items;
}

function blogCommentCreate(
    mysqli $conn,
    int $postId,
    string $name,
    string $email,
    string $content,
    ?int $customerId = null
): array {
    $name = trim($name);
    $email = trim($email);
    $content = trim($content);

    if ($name === '' || $content === '') {
        return ['ok' => false, 'message' => 'Vui lòng nhập họ tên và nội dung bình luận.'];
    }
    if (mb_strlen($content) > 2000) {
        return ['ok' => false, 'message' => 'Bình luận không được vượt quá 2000 ký tự.'];
    }

    $stmtPost = $conn->prepare('SELECT id FROM blog_posts WHERE id = ? LIMIT 1');
    if (!$stmtPost) {
        return ['ok' => false, 'message' => 'Không thể gửi bình luận.'];
    }
    $stmtPost->bind_param('i', $postId);
    $stmtPost->execute();
    $postRow = $stmtPost->get_result()->fetch_assoc();
    $stmtPost->close();
    if (!$postRow) {
        return ['ok' => false, 'message' => 'Bài viết không tồn tại.'];
    }

    $emailParam = $email !== '' ? $email : null;
    $customerIdValue = $customerId && $customerId > 0 ? $customerId : null;
    $status = 'pending';

    $stmt = $conn->prepare("INSERT INTO blog_comments
        (post_id, customer_id, author_name, author_email, content, status)
        VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Không thể gửi bình luận.'];
    }
    $stmt->bind_param('iissss', $postId, $customerIdValue, $name, $emailParam, $content, $status);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'message' => 'Không thể lưu bình luận.'];
    }

    return ['ok' => true, 'message' => 'Cảm ơn bạn! Bình luận đã gửi và chờ duyệt.'];
}

function blogCommentAdminGetAll(mysqli $conn, ?string $statusFilter = null, int $limit = 100): array
{
    $sql = "SELECT c.id, c.post_id, c.author_name, c.author_email, c.content, c.status, c.created_at,
                   p.title AS post_title, p.slug AS post_slug
            FROM blog_comments c
            JOIN blog_posts p ON p.id = c.post_id";
    if ($statusFilter !== null && $statusFilter !== '') {
        $sql .= " WHERE c.status = ?";
    }
    $sql .= " ORDER BY c.id DESC LIMIT ?";

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
        $items[] = blogCommentMapRow($row);
    }
    $stmt->close();
    return $items;
}

function blogCommentAdminUpdateStatus(mysqli $conn, int $commentId, string $status): bool
{
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        return false;
    }

    $stmt = $conn->prepare('UPDATE blog_comments SET status = ?, updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $status, $commentId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function blogCommentAdminDelete(mysqli $conn, int $commentId): bool
{
    $stmt = $conn->prepare('DELETE FROM blog_comments WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $commentId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
