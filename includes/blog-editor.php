<?php
require_once __DIR__ . '/blog-repository.php';

$editorState = blogEditorLoadState($conn);
$editorMessage = $editorState['message'];
$editorSuccess = $editorState['success'];
$f = $editorState['form'];
$categoryOptions = blogGetCategoryOptions($conn);

$coverPreviewUrl = $f['image'] !== '' ? $f['image'] : '';
$showCoverPreview = $coverPreviewUrl !== '';
$editPostId = (int) ($f['post_id'] ?? 0);
$isEditing = $editPostId > 0;
?>

<section class="container blog-editor-page admin-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <a href="<?php echo e(app_url('admin-blog')); ?>">Quản lý blog</a> / <span><?php echo $isEditing ? 'Sửa bài' : 'Viết bài mới'; ?></span></p>

    <div class="admin-page-head admin-page-head--toolbar">
        <h1><?php echo $isEditing ? 'Chỉnh sửa bài viết' : 'Viết bài blog mới'; ?></h1>
        <a class="btn-secondary" href="<?php echo e(app_url('admin-blog')); ?>">← Danh sách blog</a>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <p class="editor-intro">Soạn nội dung và đăng bài lên cửa hàng. Thời gian đọc được tính tự động từ nội dung.</p>

    <?php if ($editorMessage !== ''): ?>
        <p class="blog-editor-notice <?php echo $editorSuccess ? 'blog-editor-notice--success' : 'blog-editor-notice--error'; ?>">
            <?php echo e($editorMessage); ?>
        </p>
    <?php endif; ?>

    <form method="post" action="<?php echo e(app_url('blog-editor', $isEditing ? ['edit' => $editPostId] : [])); ?>" class="blog-editor-form" id="blogEditorForm" enctype="multipart/form-data">
        <?php echo csrfField(); ?>
        <input type="hidden" name="view" value="blog-editor">
        <input type="hidden" name="post_id" value="<?php echo $editPostId; ?>">

        <div class="blog-editor-layout">
            <div class="blog-editor-main">
                <div class="blog-editor-field">
                    <label for="blogTitle">Tiêu đề</label>
                    <input type="text" id="blogTitle" name="title" required value="<?php echo e($f['title']); ?>" placeholder="Nhập tiêu đề bài viết...">
                </div>

                <div class="blog-editor-field">
                    <label for="blogExcerpt">Mô tả ngắn</label>
                    <textarea id="blogExcerpt" name="excerpt" rows="3" required placeholder="Tóm tắt ngắn gọn cho danh sách bài viết..."><?php echo e($f['excerpt']); ?></textarea>
                </div>

                <div class="blog-editor-field">
                    <label>Nội dung bài viết</label>
                    <div class="blog-editor-compose">
                        <div class="blog-editor-toolbar" role="toolbar" aria-label="Định dạng văn bản">
                            <button type="button" data-cmd="bold" title="In đậm"><strong>B</strong></button>
                            <button type="button" data-cmd="italic" title="In nghiêng"><em>I</em></button>
                            <button type="button" data-cmd="formatBlock" data-val="h2" title="Tiêu đề H2">H2</button>
                            <button type="button" data-cmd="formatBlock" data-val="h3" title="Tiêu đề H3">H3</button>
                            <button type="button" data-cmd="insertUnorderedList" title="Danh sách">• List</button>
                            <button type="button" id="insertImageBtn" title="Chèn ảnh">Ảnh</button>
                            <button type="button" id="insertLinkBtn" title="Chèn liên kết">Link</button>
                        </div>
                        <div
                            id="editorSurface"
                            class="blog-editor-surface"
                            contenteditable="true"
                            data-placeholder="Nhập nội dung bài viết tại đây..."
                        ><?php echo $f['content_html']; ?></div>
                    </div>
                    <textarea name="content_html" id="contentHtml" class="blog-editor-html-input" required aria-hidden="true"></textarea>
                </div>
            </div>

            <aside class="blog-editor-sidebar">
                <div class="blog-editor-panel">
                    <h2>Cấu hình bài viết</h2>

                    <div class="blog-editor-field">
                        <label for="blogCategory">Danh mục</label>
                        <select id="blogCategory" name="category" required>
                            <?php foreach ($categoryOptions as $catName): ?>
                                <option value="<?php echo e($catName); ?>" <?php echo $f['category'] === $catName ? 'selected' : ''; ?>>
                                    <?php echo e($catName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="blog-editor-field">
                        <label for="blogStatus">Trạng thái</label>
                        <select id="blogStatus" name="status" required>
                            <?php
                            $statusVal = (string) ($f['status'] ?? 'published');
                            foreach (['published' => 'Đã đăng', 'draft' => 'Nháp', 'archived' => 'Lưu trữ'] as $val => $label):
                                ?>
                                <option value="<?php echo e($val); ?>" <?php echo $statusVal === $val ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="blog-editor-field">
                        <label for="blogPublishedAt">Ngày đăng</label>
                        <input type="date" id="blogPublishedAt" name="published_at" required value="<?php echo e($f['published_at']); ?>">
                    </div>

                    <div class="blog-editor-field">
                        <label for="blogSlug">Slug (URL)</label>
                        <input type="text" id="blogSlug" name="slug" required<?php echo $isEditing ? ' readonly' : ''; ?> value="<?php echo e($f['slug']); ?>" data-manual="<?php echo $isEditing ? '1' : '0'; ?>" placeholder="tu-dong-tu-tieu-de">
                    </div>

                    <div class="blog-editor-field blog-cover-upload">
                        <label>Ảnh đại diện</label>
                        <input type="hidden" name="image" id="existingImagePath" value="<?php echo e($f['image']); ?>">
                        <input type="file" id="coverImageInput" name="cover_image" class="blog-cover-input" accept="image/jpeg,image/png,image/webp,image/gif">
                        <div id="coverDropzone" class="blog-cover-dropzone" role="button" tabindex="0"<?php echo $showCoverPreview ? ' style="display:none"' : ''; ?>>
                            <svg width="32" height="32" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M19 7h-3V6a4 4 0 0 0-8 0v1H5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zm-9-1a2 2 0 0 1 4 0v1h-4V6zm5 12H9l-2.5-3.2L6 17H4l4.5-6 3 4 2-2.5L18 17h-3l-2-2z"/></svg>
                            <span>Click hoặc kéo thả để tải ảnh</span>
                            <small>JPG, PNG, WebP · Tối đa 5MB</small>
                        </div>
                        <div id="coverPreview" class="blog-cover-preview<?php echo $showCoverPreview ? ' is-visible' : ''; ?>">
                            <img id="coverPreviewImg" src="<?php echo $showCoverPreview ? e($coverPreviewUrl) : ''; ?>" alt="Xem trước ảnh đại diện">
                            <div class="blog-cover-preview-actions">
                                <button type="button" id="coverRemoveBtn" class="blog-cover-remove">Gỡ ảnh</button>
                            </div>
                        </div>
                    </div>

                    <label class="blog-editor-check">
                        <input type="checkbox" name="is_featured" value="1" <?php echo !empty($f['is_featured']) ? 'checked' : ''; ?>>
                        Đánh dấu bài nổi bật
                    </label>
                </div>
            </aside>
        </div>

        <div class="blog-editor-actions">
            <a class="btn-blog-cancel" href="<?php echo e(app_url('admin-blog')); ?>">Hủy</a>
            <button type="submit" name="save_blog_post" value="1" class="btn-blog-publish"><?php echo $isEditing ? 'Lưu thay đổi' : 'Đăng bài viết'; ?></button>
        </div>
    </form>
</section>
