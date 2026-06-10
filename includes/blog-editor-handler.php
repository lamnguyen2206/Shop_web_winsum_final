<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function blogEditorSlugify(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
    } else {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text) ?? '';
    $text = preg_replace('/[\s_-]+/', '-', $text) ?? '';
    return trim($text, '-');
}

function blogEditorSaveCoverUpload(): ?string
{
    if (empty($_FILES['cover_image']['tmp_name']) || !is_uploaded_file($_FILES['cover_image']['tmp_name'])) {
        return null;
    }

    $file = $_FILES['cover_image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return null;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!isset($allowed[$mime])) {
        return null;
    }

    $uploadDir = dirname(__DIR__) . '/assets/images/blog-uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'blog_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }

    return 'assets/images/blog-uploads/' . $filename;
}

function blogEditorDefaultForm(mysqli $conn): array
{
    require_once __DIR__ . '/blog-repository.php';
    $categoryOptions = blogGetCategoryOptions($conn);

    return [
        'post_id' => 0,
        'title' => '',
        'slug' => '',
        'excerpt' => '',
        'content_html' => '<p>Nhập nội dung bài viết tại đây...</p>',
        'image' => '',
        'category' => $categoryOptions[0] ?? 'Tin tức',
        'published_at' => date('Y-m-d'),
        'is_featured' => false,
        'status' => 'published',
    ];
}

function blogEditorFormFromPost(array $post): array
{
    $contentHtml = $post['content_html'] !== ''
        ? $post['content_html']
        : '<p>' . implode('</p><p>', array_map('htmlspecialchars', $post['content'])) . '</p>';

    return [
        'post_id' => (int) $post['id'],
        'title' => (string) $post['title'],
        'slug' => (string) $post['slug'],
        'excerpt' => (string) $post['excerpt'],
        'content_html' => $contentHtml,
        'image' => (string) $post['image'],
        'category' => (string) $post['category'],
        'published_at' => (string) $post['date'],
        'is_featured' => !empty($post['is_featured']),
        'status' => (string) ($post['status'] ?? 'published'),
    ];
}

function blogEditorHandlePost(mysqli $conn, string $view): void
{
    if ($view !== 'blog-editor' || $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['save_blog_post'])) {
        return;
    }

    require_once __DIR__ . '/blog-repository.php';

    if (!adminCurrent()) {
        adminRequire();
    }

    $postId = (int) ($_POST['post_id'] ?? 0);

    $posted = [
        'post_id' => $postId,
        'title' => trim((string) ($_POST['title'] ?? '')),
        'slug' => trim((string) ($_POST['slug'] ?? '')),
        'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
        'content_html' => (string) ($_POST['content_html'] ?? ''),
        'image' => trim((string) ($_POST['image'] ?? '')),
        'category' => trim((string) ($_POST['category'] ?? 'Tin tức')),
        'published_at' => trim((string) ($_POST['published_at'] ?? date('Y-m-d'))),
        'is_featured' => !empty($_POST['is_featured']),
        'status' => trim((string) ($_POST['status'] ?? 'published')),
    ];

    if (!csrfValidate()) {
        $_SESSION['blog_editor'] = [
            'message' => 'Phiên làm việc không hợp lệ. Vui lòng thử lại.',
            'success' => false,
            'form' => array_merge(blogEditorDefaultForm($conn), $posted),
        ];
        redirect(app_url('blog-editor', $postId > 0 ? ['edit' => $postId] : []));
    }

    $title = $posted['title'];
    $slug = $posted['slug'] !== '' ? $posted['slug'] : blogEditorSlugify($title);
    $excerpt = $posted['excerpt'];
    $contentHtml = blogSanitizeHtml(trim($posted['content_html']));
    $image = $posted['image'];
    $category = $posted['category'];
    $publishedAt = $posted['published_at'];
    $isFeatured = $posted['is_featured'];

    $uploadedImage = blogEditorSaveCoverUpload();
    if ($uploadedImage !== null) {
        $image = $uploadedImage;
    }

    $plainContent = trim(strip_tags($contentHtml));
    if ($title === '' || $slug === '' || $excerpt === '' || $plainContent === '') {
        $_SESSION['blog_editor'] = [
            'message' => 'Vui lòng nhập đầy đủ tiêu đề, slug, mô tả ngắn và nội dung.',
            'success' => false,
            'form' => array_merge(blogEditorDefaultForm($conn), $posted, [
                'slug' => $slug,
                'content_html' => $contentHtml,
            ]),
        ];
        redirect(app_url('blog-editor', $postId > 0 ? ['edit' => $postId] : []));
    }

    $status = in_array($posted['status'], ['draft', 'published', 'archived'], true)
        ? $posted['status']
        : 'published';

    if (blogSlugExists($conn, $slug, $postId)) {
        $_SESSION['blog_editor'] = [
            'message' => 'Slug đã được dùng cho bài viết khác. Vui lòng đổi slug.',
            'success' => false,
            'form' => array_merge(blogEditorDefaultForm($conn), $posted, [
                'slug' => $slug,
                'content_html' => $contentHtml,
                'image' => $image,
            ]),
        ];
        redirect(app_url('blog-editor', $postId > 0 ? ['edit' => $postId] : []));
    }

    $readTime = blogEstimateReadTime($contentHtml);
    $payload = [
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'content' => $contentHtml,
        'category' => $category !== '' ? $category : 'Tin tức',
        'image' => $image !== '' ? $image : 'assets/images/blog_1.png',
        'read_time' => $readTime,
        'published_at' => $publishedAt !== '' ? $publishedAt : date('Y-m-d'),
        'is_featured' => $isFeatured,
        'status' => $status,
    ];

    if ($postId > 0) {
        $ok = blogUpdatePost($conn, $postId, $payload);
        $successMessage = 'Đã cập nhật bài viết.';
        $failMessage = 'Không thể cập nhật bài viết.';
        $redirectParams = ['edit' => $postId];
    } else {
        $ok = blogCreatePost($conn, $payload);
        $successMessage = 'Đã đăng bài viết thành công.';
        $failMessage = 'Không thể lưu bài viết.';
        $redirectParams = [];
    }

    if ($ok) {
        $_SESSION['blog_editor'] = [
            'message' => $successMessage,
            'success' => true,
            'form' => $postId > 0
                ? blogEditorFormFromPost((array) blogGetPostById($conn, $postId))
                : blogEditorDefaultForm($conn),
        ];
        redirect(app_url('admin-blog', ['msg' => $successMessage]));
    }

    $_SESSION['blog_editor'] = [
        'message' => $failMessage,
        'success' => false,
        'form' => array_merge(blogEditorDefaultForm($conn), $posted, [
            'slug' => $slug,
            'content_html' => $contentHtml,
            'image' => $image,
        ]),
    ];

    redirect(app_url('blog-editor', $redirectParams));
}

/**
 * @return array{message:string,success:bool,form:array}
 */
function blogEditorLoadState(mysqli $conn): array
{
    if (!empty($_SESSION['blog_editor']) && is_array($_SESSION['blog_editor'])) {
        $state = $_SESSION['blog_editor'];
        unset($_SESSION['blog_editor']);
        return [
            'message' => (string) ($state['message'] ?? ''),
            'success' => (bool) ($state['success'] ?? false),
            'form' => is_array($state['form'] ?? null) ? $state['form'] : blogEditorDefaultForm($conn),
        ];
    }

    $editId = (int) ($_GET['edit'] ?? 0);
    if ($editId > 0) {
        require_once __DIR__ . '/blog-repository.php';
        $post = blogGetPostById($conn, $editId);
        if ($post) {
            return [
                'message' => '',
                'success' => false,
                'form' => blogEditorFormFromPost($post),
            ];
        }
    }

    return [
        'message' => '',
        'success' => false,
        'form' => blogEditorDefaultForm($conn),
    ];
}
