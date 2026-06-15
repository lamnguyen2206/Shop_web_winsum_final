<?php
/** @var array<int, array<string, mixed>> $adminCommentReplies */
if (empty($adminCommentReplies)) {
    return;
}
?>
<ul class="admin-thread-replies">
    <?php foreach ($adminCommentReplies as $reply): ?>
        <li>
            <article class="review-admin-card admin-comment-card--reply">
                <div class="review-admin-meta">
                    <strong><?php echo htmlspecialchars($reply['author_name']); ?></strong>
                    <span class="badge-status badge-approved">Phản hồi shop</span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p>
                <small><?php echo htmlspecialchars($reply['created_label']); ?></small>
                <div class="admin-actions-cell">
                    <form method="post" class="admin-inline-form" onsubmit="return confirm('Xóa phản hồi này?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="comment_delete">
                        <input type="hidden" name="comment_id" value="<?php echo (int) $reply['id']; ?>">
                        <button type="submit" class="link-danger">Xóa</button>
                    </form>
                </div>
            </article>
            <?php
            $adminCommentReplies = $reply['replies'] ?? [];
            include __DIR__ . '/comment-admin-replies.php';
            ?>
        </li>
    <?php endforeach; ?>
</ul>
