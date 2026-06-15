<?php

declare(strict_types=1);

function blogCommentEnsureTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS blog_comments (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        post_id BIGINT NOT NULL,
        customer_id BIGINT DEFAULT NULL,
        parent_id BIGINT DEFAULT NULL,
        is_admin_reply TINYINT(1) NOT NULL DEFAULT 0,
        author_name VARCHAR(120) NOT NULL,
        author_email VARCHAR(120) DEFAULT NULL,
        content TEXT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_blog_comments_post (post_id),
        INDEX idx_blog_comments_status (status),
        INDEX idx_blog_comments_parent (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $extraCols = [
        'parent_id' => 'BIGINT DEFAULT NULL',
        'is_admin_reply' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ];
    foreach ($extraCols as $col => $def) {
        $check = $conn->query("SHOW COLUMNS FROM blog_comments LIKE '" . $conn->real_escape_string($col) . "'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE blog_comments ADD COLUMN {$col} {$def}");
        }
        if ($check) {
            $check->free();
        }
    }

    $indexCheck = $conn->query("SHOW INDEX FROM blog_comments WHERE Key_name = 'idx_blog_comments_parent'");
    if ($indexCheck && $indexCheck->num_rows === 0) {
        $conn->query('ALTER TABLE blog_comments ADD INDEX idx_blog_comments_parent (parent_id)');
    }
    if ($indexCheck) {
        $indexCheck->free();
    }

    $conn->query("UPDATE blog_comments SET status = 'approved' WHERE status = 'pending'");
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
        'parent_id' => isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
        'parent_author_name' => $row['parent_author_name'] ?? '',
        'is_admin_reply' => !empty($row['is_admin_reply']),
        'author_name' => $row['author_name'],
        'author_email' => $row['author_email'] ?? '',
        'content' => $row['content'],
        'status' => $row['status'],
        'created_at' => $createdAt,
        'created_label' => $label,
        'replies' => [],
    ];
}

function blogCommentNestApproved(array $flatComments): array
{
    $repliesByParent = [];
    $roots = [];

    foreach ($flatComments as $comment) {
        $comment['replies'] = [];
        if ($comment['parent_id'] === null || (int) $comment['parent_id'] === 0) {
            $roots[$comment['id']] = $comment;
        } else {
            $repliesByParent[$comment['parent_id']][] = $comment;
        }
    }

    $attach = static function (array $node) use (&$attach, $repliesByParent): array {
        $children = $repliesByParent[$node['id']] ?? [];
        $node['replies'] = array_map(static fn (array $child) => $attach($child), $children);

        return $node;
    };

    return array_values(array_map($attach, $roots));
}

function blogCommentGetApprovedByPost(mysqli $conn, int $postId, int $limit = 50): array
{
    $stmt = $conn->prepare("SELECT id, post_id, parent_id, is_admin_reply, author_name, author_email, content, status, created_at
                            FROM blog_comments
                            WHERE post_id = ?
                              AND status = 'approved'
                            ORDER BY COALESCE(parent_id, id) ASC, id ASC
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

    return blogCommentNestApproved($items);
}

function blogCommentCountApprovedByPost(mysqli $conn, int $postId): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM blog_comments WHERE post_id = ? AND status = 'approved' AND parent_id IS NULL");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['c'] ?? 0);
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
    $status = 'approved';

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

    return ['ok' => true, 'message' => 'Cảm ơn bạn! Bình luận đã được đăng.'];
}

function blogCommentAdminGetAll(mysqli $conn, ?string $statusFilter = null, int $limit = 100): array
{
    $sql = "SELECT c.id, c.post_id, c.parent_id, c.is_admin_reply, c.author_name, c.author_email, c.content, c.status, c.created_at,
                   p.title AS post_title, p.slug AS post_slug,
                   parent.author_name AS parent_author_name
            FROM blog_comments c
            JOIN blog_posts p ON p.id = c.post_id
            LEFT JOIN blog_comments parent ON parent.id = c.parent_id";
    if ($statusFilter !== null && $statusFilter !== '') {
        $sql .= " WHERE c.status = ?";
    }
    $sql .= " ORDER BY COALESCE(c.parent_id, c.id) DESC, c.id ASC LIMIT ?";

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

function blogCommentAdminGetNested(mysqli $conn, int $limit = 100): array
{
    return blogCommentNestApproved(blogCommentAdminGetAll($conn, null, $limit));
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
    $stmtChildren = $conn->prepare('DELETE FROM blog_comments WHERE parent_id = ?');
    if ($stmtChildren) {
        $stmtChildren->bind_param('i', $commentId);
        $stmtChildren->execute();
        $stmtChildren->close();
    }

    $stmt = $conn->prepare('DELETE FROM blog_comments WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $commentId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Admin trả lời bình luận khách — tự duyệt và hiển thị ngay trên bài viết.
 *
 * @return array{ok:bool,message:string}
 */
function blogCommentAdminReply(mysqli $conn, int $parentId, string $content): array
{
    $content = trim($content);
    if ($parentId <= 0) {
        return ['ok' => false, 'message' => 'Bình luận không hợp lệ.'];
    }
    if ($content === '') {
        return ['ok' => false, 'message' => 'Vui lòng nhập nội dung trả lời.'];
    }
    if (mb_strlen($content) > 2000) {
        return ['ok' => false, 'message' => 'Nội dung trả lời không được vượt quá 2000 ký tự.'];
    }

    $stmtParent = $conn->prepare("SELECT id, post_id, parent_id, status, is_admin_reply FROM blog_comments WHERE id = ? LIMIT 1");
    if (!$stmtParent) {
        return ['ok' => false, 'message' => 'Không thể trả lời bình luận.'];
    }
    $stmtParent->bind_param('i', $parentId);
    $stmtParent->execute();
    $parent = $stmtParent->get_result()->fetch_assoc();
    $stmtParent->close();

    if (!$parent) {
        return ['ok' => false, 'message' => 'Không tìm thấy bình luận.'];
    }
    if (!empty($parent['is_admin_reply'])) {
        return ['ok' => false, 'message' => 'Chỉ trả lời được bình luận của khách.'];
    }

    $postId = (int) $parent['post_id'];
    $authorName = 'Winsum Home';
    $isAdminReply = 1;
    $status = 'approved';

    $stmt = $conn->prepare("INSERT INTO blog_comments
        (post_id, customer_id, parent_id, is_admin_reply, author_name, author_email, content, status)
        VALUES (?, NULL, ?, ?, ?, NULL, ?, ?)");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Không thể lưu câu trả lời.'];
    }
    $stmt->bind_param('iiisss', $postId, $parentId, $isAdminReply, $authorName, $content, $status);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'message' => 'Không thể lưu câu trả lời.'];
    }

    return ['ok' => true, 'message' => 'Đã gửi trả lời. Khách sẽ thấy ngay trên bài viết.'];
}
