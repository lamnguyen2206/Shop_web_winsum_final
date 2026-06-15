<?php
require_once __DIR__ . '/../repositories/blog-comment-repository.php';

blogCommentEnsureTable($conn);

$adminMessage = '';
$adminMessageOk = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'comment_reply') {
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $replyContent = (string) ($_POST['reply_content'] ?? '');
        $result = blogCommentAdminReply($conn, $parentId, $replyContent);
        $adminMessage = $result['message'];
        $adminMessageOk = $result['ok'];
    } elseif ($action === 'comment_delete') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        if (blogCommentAdminDelete($conn, $commentId)) {
            $adminMessage = 'Đã xóa bình luận.';
            $adminMessageOk = true;
        } else {
            $adminMessage = 'Không thể xóa bình luận.';
            $adminMessageOk = false;
        }
    }
}

$comments = blogCommentAdminGetNested($conn);
?>

<section class="container admin-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Quản trị bình luận blog</span></p>

    <div class="admin-page-head">
        <h1>Quản lý bình luận blog</h1>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice<?php echo $adminMessageOk === true ? ' admin-notice--ok' : ($adminMessageOk === false ? ' admin-notice--err' : ''); ?>">
            <?php echo htmlspecialchars($adminMessage); ?>
        </p>
    <?php endif; ?>

    <div class="admin-panel admin-panel-wide">
        <?php if (empty($comments)): ?>
            <p class="empty-state">Chưa có bình luận nào.</p>
        <?php else: ?>
            <div class="review-admin-list">
                <?php foreach ($comments as $comment): ?>
                    <div class="admin-comment-thread">
                        <article class="review-admin-card">
                            <div class="review-admin-meta">
                                <strong><?php echo htmlspecialchars($comment['author_name']); ?></strong>
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
                                <form method="post" class="admin-inline-form" onsubmit="return confirm('Xóa bình luận này?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="comment_delete">
                                    <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                                    <button type="submit" class="link-danger">Xóa</button>
                                </form>
                            </div>
                        </article>

                        <?php
                        $adminCommentReplies = $comment['replies'] ?? [];
                        include __DIR__ . '/partials/comment-admin-replies.php';
                        ?>

                        <form method="post" class="admin-comment-reply-form admin-comment-reply-form--inline">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="comment_reply">
                            <input type="hidden" name="parent_id" value="<?php echo (int) $comment['id']; ?>">
                            <label class="admin-comment-reply-label" for="comment-reply-<?php echo (int) $comment['id']; ?>">Trả lời khách</label>
                            <textarea id="comment-reply-<?php echo (int) $comment['id']; ?>" name="reply_content" rows="3" maxlength="2000" required placeholder="Nhập câu trả lời của shop..."></textarea>
                            <button type="submit">Gửi trả lời</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
