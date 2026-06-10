<?php
require_once __DIR__ . '/review-repository.php';

$adminMessage = '';
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : 'all';
if ($statusFilter === 'all') {
    $statusFilter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'review_status') {
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if (reviewAdminUpdateStatus($conn, $reviewId, $status)) {
            $adminMessage = 'Đã cập nhật trạng thái đánh giá.';
        } else {
            $adminMessage = 'Không thể cập nhật đánh giá.';
        }
    } elseif ($action === 'review_delete') {
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        if (reviewAdminDelete($conn, $reviewId)) {
            $adminMessage = 'Đã xóa đánh giá.';
        } else {
            $adminMessage = 'Không thể xóa đánh giá.';
        }
    }
}

$reviews = reviewAdminGetAll($conn, $statusFilter !== '' ? $statusFilter : null);
?>

<section class="container admin-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Quản trị đánh giá</span></p>

    <div class="admin-page-head">
        <h1>Quản lý đánh giá sản phẩm</h1>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice"><?php echo htmlspecialchars($adminMessage); ?></p>
    <?php endif; ?>

    <div class="admin-filter-tabs">
        <a class="<?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" href="index.php?view=admin-reviews&amp;status=pending">Chờ duyệt</a>
        <a class="<?php echo $statusFilter === 'approved' ? 'active' : ''; ?>" href="index.php?view=admin-reviews&amp;status=approved">Đã duyệt</a>
        <a class="<?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>" href="index.php?view=admin-reviews&amp;status=rejected">Từ chối</a>
        <a class="<?php echo $statusFilter === '' ? 'active' : ''; ?>" href="index.php?view=admin-reviews&amp;status=all">Tất cả</a>
    </div>

    <div class="admin-panel admin-panel-wide">
        <?php if (empty($reviews)): ?>
            <p class="empty-state">Không có đánh giá nào trong mục này.</p>
        <?php else: ?>
            <div class="review-admin-list">
                <?php foreach ($reviews as $review): ?>
                    <article class="review-admin-card">
                        <div class="review-admin-meta">
                            <strong><?php echo htmlspecialchars($review['reviewer_name']); ?></strong>
                            <span class="stars" aria-label="<?php echo (int) $review['rating']; ?> sao"><?php echo str_repeat('★', (int) $review['rating']); ?><?php echo str_repeat('☆', 5 - (int) $review['rating']); ?></span>
                            <span class="badge-status badge-<?php echo htmlspecialchars($review['status']); ?>"><?php echo htmlspecialchars($review['status']); ?></span>
                        </div>
                        <p class="review-product">Sản phẩm: <strong><?php echo htmlspecialchars($review['product_name']); ?></strong></p>
                        <?php if ($review['title'] !== ''): ?>
                            <h3><?php echo htmlspecialchars($review['title']); ?></h3>
                        <?php endif; ?>
                        <p><?php echo nl2br(htmlspecialchars($review['content'])); ?></p>
                        <small><?php echo htmlspecialchars((string) $review['created_at']); ?></small>
                        <div class="admin-actions-cell">
                            <?php if ($review['status'] === 'pending'): ?>
                                <form method="post" class="admin-inline-form">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="review_status">
                                    <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit">Duyệt (dữ liệu cũ)</button>
                                </form>
                                <form method="post" class="admin-inline-form">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="review_status">
                                    <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn-secondary">Từ chối (dữ liệu cũ)</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" class="admin-inline-form" onsubmit="return confirm('Xóa đánh giá này?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="review_delete">
                                <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                <button type="submit" class="link-danger">Xóa</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
