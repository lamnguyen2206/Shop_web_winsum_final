<?php
require_once __DIR__ . '/../blog-repository.php';
require_once __DIR__ . '/../blog-comment-repository.php';
require_once __DIR__ . '/../customer-auth.php';
require_once __DIR__ . '/../admin-auth.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$currentPost = blogGetPostBySlug($conn, $slug);
$postFlash = pageFlashConsume('post');
$commentNotice = $postFlash['message'];
$commentSuccess = $postFlash['success'];
$currentCustomer = customerCurrent($conn);
$postAdminView = adminCurrent();

if ($currentPost === null) {
    http_response_code(404);
    ?>
    <section class="container post-not-found">
        <h1>Không tìm thấy bài viết</h1>
        <p>Bài viết bạn đang tìm không tồn tại hoặc đã được cập nhật đường dẫn.</p>
        <a href="<?php echo e(app_url('blog')); ?>" class="read-more">Quay lại trang blog</a>
    </section>
    <?php
    return;
}

$relatedPosts = blogGetRelatedPosts($conn, $currentPost['category'], $currentPost['slug'], 3);
$postComments = blogCommentGetApprovedByPost($conn, (int) $currentPost['id']);
$commentCount = blogCommentCountApprovedByPost($conn, (int) $currentPost['id']);
?>

<section class="container blog-detail">
    <p class="breadcrumb">
        <a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> /
        <a href="<?php echo e(app_url('blog')); ?>">Tin tức</a> /
        <span><?php echo htmlspecialchars($currentPost['title']); ?></span>
    </p>

    <article class="post-article">
        <div class="post-header">
            <span class="post-category"><?php echo htmlspecialchars($currentPost['category']); ?></span>
            <h1><?php echo htmlspecialchars($currentPost['title']); ?></h1>
            <p class="meta"><?php echo htmlspecialchars($currentPost['date_label']); ?> · <?php echo htmlspecialchars($currentPost['read_time']); ?></p>
        </div>

        <div class="post-content">
            <?php if (!empty($currentPost['content_html'])): ?>
                <?php echo blogSanitizeHtml($currentPost['content_html']); ?>
            <?php else: ?>
                <?php foreach ($currentPost['content'] as $paragraph): ?>
                    <p><?php echo htmlspecialchars($paragraph); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </article>

    <section class="post-comments" id="post-comments">
        <h2>Bình luận (<?php echo $commentCount; ?>)</h2>

        <?php if ($commentNotice !== ''): ?>
            <p class="post-comment-notice<?php echo $commentSuccess ? ' is-success' : ''; ?>"><?php echo htmlspecialchars($commentNotice); ?></p>
        <?php endif; ?>

        <?php if (empty($postComments)): ?>
            <p class="post-comments-empty">Chưa có bình luận. Hãy là người đầu tiên chia sẻ ý kiến!</p>
        <?php else: ?>
            <ul class="post-comment-list">
                <?php foreach ($postComments as $comment): ?>
                    <li class="post-comment-item">
                        <div class="post-comment-head">
                            <strong><?php echo htmlspecialchars($comment['author_name']); ?></strong>
                            <time datetime="<?php echo htmlspecialchars($comment['created_at']); ?>"><?php echo htmlspecialchars($comment['created_label']); ?></time>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                        <?php
                        $threadReplies = $comment['replies'] ?? [];
                        include __DIR__ . '/partials/blog-comment-thread.php';
                        ?>
                        <?php if ($postAdminView): ?>
                            <form method="post" action="<?php echo e(app_url('post', ['slug' => $currentPost['slug']])); ?>#post-comments" class="storefront-admin-reply-form">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="admin_comment_reply">
                                <input type="hidden" name="post_slug" value="<?php echo htmlspecialchars($currentPost['slug']); ?>">
                                <input type="hidden" name="parent_id" value="<?php echo (int) $comment['id']; ?>">
                                <label for="comment-reply-<?php echo (int) $comment['id']; ?>">Trả lời khách (Winsum Home)</label>
                                <textarea id="comment-reply-<?php echo (int) $comment['id']; ?>" name="reply_content" rows="3" maxlength="2000" required placeholder="Nhập phản hồi của shop..."></textarea>
                                <button type="submit">Gửi trả lời</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="post-comment-form-wrap">
            <h3>Viết bình luận</h3>
            <form method="post" action="<?php echo e(app_url('post', ['slug' => $currentPost['slug']])); ?>#post-comments" class="post-comment-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="submit_comment">
                <input type="hidden" name="post_slug" value="<?php echo htmlspecialchars($currentPost['slug']); ?>">
                <div class="post-comment-form-grid">
                    <label>Họ tên
                        <input type="text" name="author_name" required maxlength="120" value="<?php echo htmlspecialchars((string) ($currentCustomer['full_name'] ?? '')); ?>">
                    </label>
                    <label>Email (tuỳ chọn)
                        <input type="email" name="author_email" maxlength="120" value="<?php echo htmlspecialchars((string) ($currentCustomer['email'] ?? '')); ?>">
                    </label>
                </div>
                <label>Nội dung
                    <textarea name="comment_content" rows="4" required maxlength="2000" placeholder="Chia sẻ suy nghĩ của bạn về bài viết..."></textarea>
                </label>
                <button type="submit">Gửi bình luận</button>
                <p class="form-hint">Bình luận sẽ hiển thị ngay sau khi gửi.</p>
            </form>
        </div>
    </section>

    <?php if (!empty($relatedPosts)): ?>
        <section class="related-posts">
            <h2>Bài viết liên quan</h2>
            <div class="blog-list">
                <?php foreach ($relatedPosts as $post): ?>
                    <article class="blog-card">
                        <a href="index.php?view=post&amp;slug=<?php echo urlencode($post['slug']); ?>" class="blog-thumb">
                            <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                        </a>
                        <span class="post-category"><?php echo htmlspecialchars($post['category']); ?></span>
                        <h3><a href="index.php?view=post&amp;slug=<?php echo urlencode($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a></h3>
                        <p class="meta"><?php echo htmlspecialchars($post['date_label']); ?> · <?php echo htmlspecialchars($post['read_time']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
