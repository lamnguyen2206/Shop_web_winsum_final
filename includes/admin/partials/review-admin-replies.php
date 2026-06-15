<?php
/** @var array<int, array<string, mixed>> $adminReviewReplies */
if (empty($adminReviewReplies)) {
    return;
}
?>
<ul class="admin-thread-replies">
    <?php foreach ($adminReviewReplies as $reply): ?>
        <li>
            <article class="review-admin-card admin-comment-card--reply">
                <div class="review-admin-meta">
                    <strong><?php echo htmlspecialchars($reply['reviewer_name']); ?></strong>
                    <span class="badge-status badge-approved">Phản hồi shop</span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p>
                <small><?php echo htmlspecialchars($reply['created_label'] !== '' ? $reply['created_label'] : (string) $reply['created_at']); ?></small>
                <div class="admin-actions-cell">
                    <form method="post" class="admin-inline-form" onsubmit="return confirm('Xóa phản hồi này?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="review_delete">
                        <input type="hidden" name="review_id" value="<?php echo (int) $reply['id']; ?>">
                        <button type="submit" class="link-danger">Xóa</button>
                    </form>
                </div>
            </article>
            <?php
            $adminReviewReplies = $reply['replies'] ?? [];
            include __DIR__ . '/review-admin-replies.php';
            ?>
        </li>
    <?php endforeach; ?>
</ul>
