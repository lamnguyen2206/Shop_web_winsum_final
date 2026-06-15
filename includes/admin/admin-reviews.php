<?php
require_once __DIR__ . '/../repositories/review-repository.php';

$adminMessage = '';
$adminMessageOk = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'review_reply') {
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $replyContent = (string) ($_POST['reply_content'] ?? '');
        $result = reviewAdminReply($conn, $parentId, $replyContent);
        $adminMessage = $result['message'];
        $adminMessageOk = $result['ok'];
    } elseif ($action === 'review_delete') {
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        if (reviewAdminDelete($conn, $reviewId)) {
            $adminMessage = 'Đã xóa đánh giá.';
            $adminMessageOk = true;
        } else {
            $adminMessage = 'Không thể xóa đánh giá.';
            $adminMessageOk = false;
        }
    }
}

$reviews = reviewAdminGetNested($conn);
?>

<section class="container admin-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Quản trị đánh giá</span></p>

    <div class="admin-page-head">
        <h1>Quản lý đánh giá sản phẩm</h1>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice<?php echo $adminMessageOk === true ? ' admin-notice--ok' : ($adminMessageOk === false ? ' admin-notice--err' : ''); ?>">
            <?php echo htmlspecialchars($adminMessage); ?>
        </p>
    <?php endif; ?>

    <div class="admin-panel admin-panel-wide">
        <?php if (empty($reviews)): ?>
            <p class="empty-state">Chưa có đánh giá nào.</p>
        <?php else: ?>
            <div class="review-admin-list">
                <?php foreach ($reviews as $review): ?>
                    <div class="admin-comment-thread">
                        <article class="review-admin-card">
                            <div class="review-admin-meta">
                                <strong><?php echo htmlspecialchars($review['reviewer_name']); ?></strong>
                                <?php if ((int) $review['rating'] > 0): ?>
                                    <span class="stars" aria-label="<?php echo (int) $review['rating']; ?> sao"><?php echo str_repeat('★', (int) $review['rating']); ?><?php echo str_repeat('☆', 5 - (int) $review['rating']); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="review-product">
                                Sản phẩm:
                                <a href="<?php echo e(app_url('product', ['slug' => $review['product_slug']])); ?>" target="_blank" rel="noopener">
                                    <strong><?php echo htmlspecialchars($review['product_name']); ?></strong>
                                </a>
                            </p>
                            <?php if ($review['title'] !== ''): ?>
                                <h3><?php echo htmlspecialchars($review['title']); ?></h3>
                            <?php endif; ?>
                            <p><?php echo nl2br(htmlspecialchars($review['content'])); ?></p>
                            <small><?php echo htmlspecialchars($review['created_label'] !== '' ? $review['created_label'] : (string) $review['created_at']); ?></small>
                            <div class="admin-actions-cell">
                                <form method="post" class="admin-inline-form" onsubmit="return confirm('Xóa đánh giá này?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="review_delete">
                                    <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                    <button type="submit" class="link-danger">Xóa</button>
                                </form>
                            </div>
                        </article>

                        <?php
                        $adminReviewReplies = $review['replies'] ?? [];
                        include __DIR__ . '/partials/review-admin-replies.php';
                        ?>

                        <form method="post" class="admin-comment-reply-form admin-comment-reply-form--inline">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="review_reply">
                            <input type="hidden" name="parent_id" value="<?php echo (int) $review['id']; ?>">
                            <label class="admin-comment-reply-label" for="review-reply-<?php echo (int) $review['id']; ?>">Trả lời khách</label>
                            <textarea id="review-reply-<?php echo (int) $review['id']; ?>" name="reply_content" rows="3" maxlength="2000" required placeholder="Nhập câu trả lời của shop..."></textarea>
                            <button type="submit">Gửi trả lời</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
