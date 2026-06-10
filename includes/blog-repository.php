<?php
require_once __DIR__ . '/../config/database.php';

function blogFormatVietnameseDateLabel(string $ymdDate): string
{
    $timestamp = strtotime($ymdDate);
    $dayMap = [
        'Monday' => 'Thứ Hai',
        'Tuesday' => 'Thứ Ba',
        'Wednesday' => 'Thứ Tư',
        'Thursday' => 'Thứ Năm',
        'Friday' => 'Thứ Sáu',
        'Saturday' => 'Thứ Bảy',
        'Sunday' => 'Chủ Nhật'
    ];

    $englishDay = date('l', $timestamp);
    $dayLabel = $dayMap[$englishDay] ?? $englishDay;
    return $dayLabel . ', ' . date('d/m/Y', $timestamp);
}

function blogMapPostRow(array $row): array
{
    $rawContent = (string) $row['content'];
    $contentBlocks = preg_split("/\r\n|\n|\r/", $rawContent);
    $hasHtml = preg_match('/<\s*(p|h1|h2|h3|h4|img|ul|ol|li|blockquote|strong|em|a|br)\b/i', $rawContent) === 1;

    return [
        'id' => (int) $row['id'],
        'slug' => $row['slug'],
        'title' => $row['title'],
        'excerpt' => $row['excerpt'],
        'content' => $contentBlocks,
        'content_html' => $hasHtml ? $rawContent : '',
        'category' => $row['category'],
        'image' => $row['image'],
        'read_time' => $row['read_time'],
        'date' => $row['published_at'],
        'date_label' => blogFormatVietnameseDateLabel($row['published_at']),
        'is_featured' => (int) $row['is_featured'] === 1
    ];
}

function blogSanitizeHtml(string $html): string
{
    $allowed = '<p><br><strong><em><u><h2><h3><h4><ul><ol><li><blockquote><img><a>';
    $clean = strip_tags($html, $allowed);

    $clean = preg_replace_callback('/<a\s+([^>]+)>/i', static function (array $matches): string {
        $attrs = $matches[1];
        $href = '';
        if (preg_match('/href\s*=\s*"([^"]*)"/i', $attrs, $hrefMatch)) {
            $href = $hrefMatch[1];
        } elseif (preg_match("/href\s*=\s*'([^']*)'/i", $attrs, $hrefMatch)) {
            $href = $hrefMatch[1];
        }
        $href = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">';
    }, $clean) ?? $clean;

    $clean = preg_replace_callback('/<img\s+([^>]+)>/i', static function (array $matches): string {
        $attrs = $matches[1];
        $src = '';
        $alt = '';
        if (preg_match('/src\s*=\s*"([^"]*)"/i', $attrs, $srcMatch)) {
            $src = $srcMatch[1];
        } elseif (preg_match("/src\s*=\s*'([^']*)'/i", $attrs, $srcMatch)) {
            $src = $srcMatch[1];
        }
        if (preg_match('/alt\s*=\s*"([^"]*)"/i', $attrs, $altMatch)) {
            $alt = $altMatch[1];
        } elseif (preg_match("/alt\s*=\s*'([^']*)'/i", $attrs, $altMatch)) {
            $alt = $altMatch[1];
        }
        if ($src === '') {
            return '';
        }
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '">';
    }, $clean) ?? $clean;

    return $clean;
}

function blogGetCategoryOptions(mysqli $conn): array
{
    $categories = [];
    $result = $conn->query('SELECT name FROM blog_categories WHERE is_active = 1 ORDER BY name ASC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = (string) $row['name'];
        }
    }
    if ($categories === []) {
        $categories = ['Tin tức', 'Xu hướng', 'Hướng dẫn', 'Không gian sống'];
    }
    return $categories;
}

function blogEnsureCategories(mysqli $conn): void
{
    $defaults = ['Tin tức', 'Xu hướng', 'Hướng dẫn', 'Không gian sống'];
    $stmt = $conn->prepare('SELECT id FROM blog_categories WHERE name = ? LIMIT 1');
    $insert = $conn->prepare('INSERT INTO blog_categories (name, slug, is_active) VALUES (?, ?, 1)');
    if (!$stmt || !$insert) {
        return;
    }

    foreach ($defaults as $name) {
        $stmt->bind_param('s', $name);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            continue;
        }
        $slug = strtolower(trim(preg_replace('/[\s_]+/', '-', $name) ?? $name, '-'));
        $insert->bind_param('ss', $name, $slug);
        $insert->execute();
    }

    $stmt->close();
    $insert->close();
}

function blogSeedIfEmpty(mysqli $conn): void
{
    $result = $conn->query('SELECT COUNT(*) AS c FROM blog_posts');
    if (!$result) {
        return;
    }
    $count = (int) ($result->fetch_assoc()['c'] ?? 0);
    if ($count > 0) {
        return;
    }

    $dataFile = __DIR__ . '/blog-data.php';
    if (!is_file($dataFile)) {
        return;
    }

    require $dataFile;
    if (empty($blogPosts) || !is_array($blogPosts)) {
        return;
    }

    $featuredSlugs = [
        'den-treo-tran-axis-thong-minh',
        've-dep-den-bauhaus',
        'ph5-pendant-lamp-tuyet-tac-anh-sang',
    ];

    foreach ($blogPosts as $post) {
        $contentHtml = '';
        foreach ($post['content'] as $paragraph) {
            $contentHtml .= '<p>' . htmlspecialchars((string) $paragraph, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        blogCreatePost($conn, [
            'slug' => (string) $post['slug'],
            'title' => (string) $post['title'],
            'excerpt' => (string) $post['excerpt'],
            'content' => $contentHtml,
            'category' => (string) $post['category'],
            'image' => (string) $post['image'],
            'read_time' => (string) $post['read_time'],
            'published_at' => (string) $post['date'],
            'is_featured' => in_array($post['slug'], $featuredSlugs, true),
            'status' => 'published',
        ]);
    }
}

function blogEnsureDefaults(mysqli $conn): void
{
    blogEnsureCategories($conn);
    blogSeedIfEmpty($conn);
}

function blogEstimateReadTime(string $html): string
{
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($text === '') {
        return '1 phút đọc';
    }
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $wordCount = is_array($words) ? count($words) : 0;
    $minutes = max(1, (int) ceil($wordCount / 200));

    return $minutes . ' phút đọc';
}

function blogPublishedWhereSql(): string
{
    return "status = 'published'";
}

function blogCreatePost(mysqli $conn, array $payload): bool
{
    $status = in_array($payload['status'] ?? '', ['draft', 'published', 'archived'], true)
        ? $payload['status']
        : 'published';

    $stmt = $conn->prepare("INSERT INTO blog_posts
        (slug, title, excerpt, content, category, image, read_time, published_at, is_featured, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }

    $slug = $payload['slug'];
    $title = $payload['title'];
    $excerpt = $payload['excerpt'];
    $content = $payload['content'];
    $category = $payload['category'];
    $image = $payload['image'];
    $readTime = $payload['read_time'];
    $publishedAt = $payload['published_at'];
    $isFeatured = !empty($payload['is_featured']) ? 1 : 0;

    $stmt->bind_param('ssssssssis', $slug, $title, $excerpt, $content, $category, $image, $readTime, $publishedAt, $isFeatured, $status);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function blogUpdatePost(mysqli $conn, int $id, array $payload): bool
{
    $status = in_array($payload['status'] ?? '', ['draft', 'published', 'archived'], true)
        ? $payload['status']
        : 'published';

    $stmt = $conn->prepare("UPDATE blog_posts SET
        slug = ?, title = ?, excerpt = ?, content = ?, category = ?, image = ?,
        read_time = ?, published_at = ?, is_featured = ?, status = ?
        WHERE id = ?");
    if (!$stmt) {
        return false;
    }

    $slug = $payload['slug'];
    $title = $payload['title'];
    $excerpt = $payload['excerpt'];
    $content = $payload['content'];
    $category = $payload['category'];
    $image = $payload['image'];
    $readTime = $payload['read_time'];
    $publishedAt = $payload['published_at'];
    $isFeatured = !empty($payload['is_featured']) ? 1 : 0;

    $stmt->bind_param(
        'ssssssssisi',
        $slug,
        $title,
        $excerpt,
        $content,
        $category,
        $image,
        $readTime,
        $publishedAt,
        $isFeatured,
        $status,
        $id
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function blogSlugExists(mysqli $conn, string $slug, int $excludeId = 0): bool
{
    if ($excludeId > 0) {
        $stmt = $conn->prepare('SELECT id FROM blog_posts WHERE slug = ? AND id <> ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $slug, $excludeId);
    } else {
        $stmt = $conn->prepare('SELECT id FROM blog_posts WHERE slug = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $slug);
    }
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $exists;
}

function blogGetPostById(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT id, slug, title, excerpt, content, category, image, read_time, published_at, is_featured, status
                            FROM blog_posts
                            WHERE id = ?
                            LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $mapped = blogMapPostRow($row);
    $mapped['status'] = (string) ($row['status'] ?? 'published');

    return $mapped;
}

/**
 * @return list<array<string, mixed>>
 */
function blogAdminList(mysqli $conn, ?string $statusFilter = null, string $search = ''): array
{
    $sql = "SELECT id, slug, title, excerpt, category, image, read_time, published_at, is_featured, status, created_at
            FROM blog_posts
            WHERE 1=1";
    $types = '';
    $params = [];

    if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
        $sql .= ' AND status = ?';
        $types .= 's';
        $params[] = $statusFilter;
    }

    $search = trim($search);
    if ($search !== '') {
        $sql .= ' AND (title LIKE ? OR slug LIKE ? OR category LIKE ?)';
        $types .= 'sss';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= ' ORDER BY published_at DESC, id DESC';

    if ($types === '') {
        $result = $conn->query($sql);
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'slug' => $row['slug'],
                'title' => $row['title'],
                'excerpt' => $row['excerpt'],
                'category' => $row['category'],
                'image' => $row['image'],
                'read_time' => $row['read_time'],
                'published_at' => $row['published_at'],
                'date_label' => blogFormatVietnameseDateLabel($row['published_at']),
                'is_featured' => (int) $row['is_featured'] === 1,
                'status' => (string) ($row['status'] ?? 'published'),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'excerpt' => $row['excerpt'],
            'category' => $row['category'],
            'image' => $row['image'],
            'read_time' => $row['read_time'],
            'published_at' => $row['published_at'],
            'date_label' => blogFormatVietnameseDateLabel($row['published_at']),
            'is_featured' => (int) $row['is_featured'] === 1,
            'status' => (string) ($row['status'] ?? 'published'),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }, $rows);
}

function blogAdminDelete(mysqli $conn, int $id): bool
{
    $stmt = $conn->prepare('DELETE FROM blog_posts WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function blogAdminSetStatus(mysqli $conn, int $id, string $status): bool
{
    if (!in_array($status, ['draft', 'published', 'archived'], true)) {
        return false;
    }
    $stmt = $conn->prepare('UPDATE blog_posts SET status = ? WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $status, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function blogAdminToggleFeatured(mysqli $conn, int $id): bool
{
    $stmt = $conn->prepare('UPDATE blog_posts SET is_featured = IF(is_featured = 1, 0, 1) WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function blogStatusLabel(string $status): string
{
    $map = [
        'draft' => 'Nháp',
        'published' => 'Đã đăng',
        'archived' => 'Lưu trữ',
    ];
    return $map[$status] ?? $status;
}

function blogGetAllPosts(mysqli $conn): array
{
    $sql = "SELECT id, slug, title, excerpt, content, category, image, read_time, published_at, is_featured
            FROM blog_posts
            WHERE " . blogPublishedWhereSql() . "
            ORDER BY published_at DESC, id DESC";
    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = blogMapPostRow($row);
    }
    return $posts;
}

function blogGetFeaturedPosts(mysqli $conn, int $limit = 3): array
{
    $stmt = $conn->prepare("SELECT id, slug, title, excerpt, content, category, image, read_time, published_at, is_featured
                            FROM blog_posts
                            WHERE is_featured = 1 AND " . blogPublishedWhereSql() . "
                            ORDER BY published_at DESC, id DESC
                            LIMIT ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = blogMapPostRow($row);
    }
    $stmt->close();
    return $posts;
}

function blogGetPostBySlug(mysqli $conn, string $slug): ?array
{
    $stmt = $conn->prepare("SELECT id, slug, title, excerpt, content, category, image, read_time, published_at, is_featured
                            FROM blog_posts
                            WHERE slug = ? AND " . blogPublishedWhereSql() . "
                            LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ? blogMapPostRow($row) : null;
}

function blogGetRelatedPosts(mysqli $conn, string $category, string $excludedSlug, int $limit = 3): array
{
    $stmt = $conn->prepare("SELECT id, slug, title, excerpt, content, category, image, read_time, published_at, is_featured
                            FROM blog_posts
                            WHERE category = ? AND slug <> ? AND " . blogPublishedWhereSql() . "
                            ORDER BY published_at DESC, id DESC
                            LIMIT ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ssi', $category, $excludedSlug, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = blogMapPostRow($row);
    }
    $stmt->close();
    return $posts;
}
