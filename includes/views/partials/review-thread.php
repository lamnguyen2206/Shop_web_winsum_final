<?php
/** @var array<int, array<string, mixed>> $threadReplies */
if (empty($threadReplies)) {
    return;
}
?>
<ul class="review-replies">
    <?php foreach ($threadReplies as $reply): ?>
        <li class="review-item review-item--reply<?php echo !empty($reply['is_admin_reply']) ? ' review-item--admin' : ''; ?>">
            <div class="review-item-head">
                <strong><?php echo htmlspecialchars($reply['reviewer_name']); ?></strong>
                <?php if (!empty($reply['is_admin_reply'])): ?>
                    <span class="review-shop-badge">Phản hồi từ shop</span>
                <?php endif; ?>
                <time><?php echo htmlspecialchars((string) $reply['created_at']); ?></time>
            </div>
            <p><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p>
            <?php
            $threadReplies = $reply['replies'] ?? [];
            include __DIR__ . '/review-thread.php';
            ?>
        </li>
    <?php endforeach; ?>
</ul>
