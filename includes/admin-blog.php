<?php
require_once __DIR__ . '/blog-repository.php';

$adminMessage = isset($_GET['msg']) ? trim((string) $_GET['msg']) : '';
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : 'all';
$searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$filterStatus = $statusFilter === 'all' ? null : $statusFilter;
$posts = blogAdminList($conn, $filterStatus, $searchQuery);
?>

<section class="container admin-page admin-blog-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Quản lý blog</span></p>

    <div class="admin-page-head admin-page-head--toolbar">
        <h1>Quản lý blog</h1>
        <a class="btn-secondary" href="<?php echo e(app_url('blog-editor')); ?>">+ Viết bài mới</a>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice"><?php echo htmlspecialchars($adminMessage); ?></p>
    <?php endif; ?>

    <div class="admin-panel admin-panel-wide">
        <form method="get" action="index.php" class="admin-blog-toolbar">
            <input type="hidden" name="view" value="admin-blog">
            <label class="admin-blog-search">
                <span class="visually-hidden">Tìm bài viết</span>
                <input type="search" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Tìm theo tiêu đề, slug, danh mục...">
            </label>
            <?php if ($statusFilter !== 'all'): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
            <?php endif; ?>
            <button type="submit">Tìm</button>
            <?php if ($searchQuery !== ''): ?>
                <a class="btn-secondary" href="<?php echo e(app_url('admin-blog', $statusFilter !== 'all' ? ['status' => $statusFilter] : [])); ?>">Xóa tìm kiếm</a>
            <?php endif; ?>
        </form>

        <div class="admin-filter-tabs">
            <a class="<?php echo $statusFilter === 'all' ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-blog', array_filter(['q' => $searchQuery !== '' ? $searchQuery : null]))); ?>">Tất cả</a>
            <a class="<?php echo $statusFilter === 'published' ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-blog', array_filter(['status' => 'published', 'q' => $searchQuery !== '' ? $searchQuery : null]))); ?>">Đã đăng</a>
            <a class="<?php echo $statusFilter === 'draft' ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-blog', array_filter(['status' => 'draft', 'q' => $searchQuery !== '' ? $searchQuery : null]))); ?>">Nháp</a>
            <a class="<?php echo $statusFilter === 'archived' ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-blog', array_filter(['status' => 'archived', 'q' => $searchQuery !== '' ? $searchQuery : null]))); ?>">Lưu trữ</a>
        </div>

        <?php if (empty($posts)): ?>
            <p class="empty-state">
                Chưa có bài viết nào<?php echo $searchQuery !== '' ? ' khớp từ khóa tìm kiếm' : ''; ?>.
                <?php if ($searchQuery === '' && $statusFilter === 'all'): ?>
                    <a href="<?php echo e(app_url('blog-editor')); ?>">Viết bài đầu tiên →</a>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table admin-blog-table">
                    <thead>
                        <tr>
                            <th>Ảnh</th>
                            <th>Tiêu đề</th>
                            <th>Danh mục</th>
                            <th>Ngày đăng</th>
                            <th>Trạng thái</th>
                            <th>Nổi bật</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td class="admin-blog-thumb">
                                    <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="" width="56" height="42" loading="lazy">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($post['title']); ?></strong>
                                    <small class="admin-blog-slug">/<?php echo htmlspecialchars($post['slug']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($post['category']); ?></td>
                                <td><?php echo htmlspecialchars($post['date_label']); ?></td>
                                <td>
                                    <span class="badge-status badge-status--<?php echo htmlspecialchars($post['status']); ?>">
                                        <?php echo htmlspecialchars(blogStatusLabel($post['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo !empty($post['is_featured']) ? '★' : '—'; ?></td>
                                <td class="admin-actions-cell">
                                    <a href="<?php echo e(app_url('post', ['slug' => $post['slug']])); ?>" target="_blank" rel="noopener">Xem</a>
                                    <a href="<?php echo e(app_url('blog-editor', ['edit' => (int) $post['id']])); ?>">Sửa</a>
                                    <form method="post" action="<?php echo e(app_url('admin-blog')); ?>" class="admin-inline-form">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="view" value="admin-blog">
                                        <input type="hidden" name="action" value="toggle_blog_featured">
                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                        <button type="submit" class="link-muted"><?php echo !empty($post['is_featured']) ? 'Bỏ nổi bật' : 'Nổi bật'; ?></button>
                                    </form>
                                    <?php if ($post['status'] !== 'published'): ?>
                                        <form method="post" action="<?php echo e(app_url('admin-blog')); ?>" class="admin-inline-form">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="view" value="admin-blog">
                                            <input type="hidden" name="action" value="set_blog_status">
                                            <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                            <input type="hidden" name="status" value="published">
                                            <button type="submit">Đăng</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($post['status'] !== 'archived'): ?>
                                        <form method="post" action="<?php echo e(app_url('admin-blog')); ?>" class="admin-inline-form">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="view" value="admin-blog">
                                            <input type="hidden" name="action" value="set_blog_status">
                                            <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                            <input type="hidden" name="status" value="archived">
                                            <button type="submit" class="btn-secondary">Lưu trữ</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?php echo e(app_url('admin-blog')); ?>" class="admin-inline-form" onsubmit="return confirm('Xóa vĩnh viễn bài viết này?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="view" value="admin-blog">
                                        <input type="hidden" name="action" value="delete_blog_post">
                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                        <button type="submit" class="link-danger">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
