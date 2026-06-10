<?php
require_once __DIR__ . '/blog-comment-repository.php';

blogCommentEnsureTable($conn);

$adminMessage = '';
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : 'pending';
if ($statusFilter === 'all') {
    $statusFilter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'comment_status') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if (blogCommentAdminUpdateStatus($conn, $commentId, $status)) {
            $adminMessage = 'Đã cập nhật trạng thái bình luận.';
        } else {
            $adminMessage = 'Không thể cập nhật bình luận.';
        }
    } elseif ($action === 'comment_delete') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        if (blogCommentAdminDelete($conn, $commentId)) {
            $adminMessage = 'Đã xóa bình luận.';
        } else {
            $adminMessage = 'Không thể xóa bình luận.';
        }
    }
}

$comments = blogCommentAdminGetAll($conn, $statusFilter !== '' ? $statusFilter : null);
?>

<section class="container admin-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Quản trị bình luận blog</span></p>

    <div class="admin-page-head">
        <h1>Quản lý bình luận blog</h1>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice"><?php echo htmlspecialchars($adminMessage); ?></p>
    <?php endif; ?>

    <div class="admin-filter-tabs">
        <a class="<?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" href="index.php?view=admin-blog-comments&amp;status=pending">Chờ duyệt</a>
        <a class="<?php echo $statusFilter === 'approved' ? 'active' : ''; ?>" href="index.php?view=admin-blog-comments&amp;status=approved">Đã duyệt</a>
        <a class="<?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>" href="index.php?view=admin-blog-comments&amp;status=rejected">Từ chối</a>
        <a class="<?php echo $statusFilter === '' ? 'active' : ''; ?>" href="index.php?view=admin-blog-comments&amp;status=all">Tất cả</a>
    </div>

    <div class="admin-panel admin-panel-wide">
        <?php if (empty($comments)): ?>
            <p class="empty-state">Không có bình luận nào trong mục này.</p>
        <?php else: ?>
            <div class="review-admin-list">
                <?php foreach ($comments as $comment): ?>
                    <article class="review-admin-card">
                        <div class="review-admin-meta">
                            <strong><?php echo htmlspecialchars($comment['author_name']); ?></strong>
                            <span class="badge-status badge-<?php echo htmlspecialchars($comment['status']); ?>"><?php echo htmlspecialchars($comment['status']); ?></span>
                        </div>
                        <p class="review-product">
                            Bài viết:
                            <a href="<?php echo e(app_url('post', ['slug' => $comment['post_slug']])); ?>" target="_blank" rel="noopener">
                                <strong><?php echo htmlspecialchars($comment['post_title']); ?></strong>
                            </a>
                        </p>
                        <p><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                        <small><?php echo htmlspecialchars($comment['created_label']); ?></small>
                        <div class="admin-actions-cell">
                            <?php if ($comment['status'] !== 'approved'): ?>
                                <form method="post" class="admin-inline-form">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="comment_status">
                                    <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit">Duyệt</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($comment['status'] !== 'rejected'): ?>
                                <form method="post" class="admin-inline-form">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="comment_status">
                                    <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn-secondary">Từ chối</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" class="admin-inline-form" onsubmit="return confirm('Xóa bình luận này?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="comment_delete">
                                <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                                <button type="submit" class="link-danger">Xóa</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
