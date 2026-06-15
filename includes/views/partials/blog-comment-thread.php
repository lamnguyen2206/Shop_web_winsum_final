<?php
/** @var array<int, array<string, mixed>> $threadReplies */
if (empty($threadReplies)) {
    return;
}
?>
<ul class="post-comment-replies">
    <?php foreach ($threadReplies as $reply): ?>
        <li class="post-comment-item post-comment-item--reply<?php echo !empty($reply['is_admin_reply']) ? ' post-comment-item--admin' : ''; ?>">
            <div class="post-comment-head">
                <strong><?php echo htmlspecialchars($reply['author_name']); ?></strong>
                <?php if (!empty($reply['is_admin_reply'])): ?>
                    <span class="post-comment-shop-badge">Phản hồi từ shop</span>
                <?php endif; ?>
                <time datetime="<?php echo htmlspecialchars($reply['created_at']); ?>"><?php echo htmlspecialchars($reply['created_label']); ?></time>
            </div>
            <p><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p>
            <?php
            $threadReplies = $reply['replies'] ?? [];
            include __DIR__ . '/blog-comment-thread.php';
            ?>
        </li>
    <?php endforeach; ?>
</ul>
