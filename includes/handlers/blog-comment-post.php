<?php

declare(strict_types=1);

function storefrontHandleBlogPostPost(mysqli $conn): void
{
    $action = (string) ($_POST['action'] ?? '');
    if (!in_array($action, ['submit_comment', 'admin_comment_reply'], true)) {
        return;
    }

    require_once __DIR__ . '/../blog-repository.php';
    require_once __DIR__ . '/../blog-comment-repository.php';
    require_once __DIR__ . '/../customer-auth.php';
    require_once __DIR__ . '/../admin-auth.php';
    require_once __DIR__ . '/../csrf.php';

    $slug = trim((string) ($_GET['slug'] ?? $_POST['post_slug'] ?? ''));
    $post = $slug !== '' ? blogGetPostBySlug($conn, $slug) : null;
    if (!$post) {
        return;
    }

    $notice = '';
    $success = false;

    if (!csrfValidate()) {
        $notice = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } elseif ($action === 'admin_comment_reply') {
        if (!adminCurrent()) {
            $notice = 'Bạn cần đăng nhập quản trị để trả lời.';
        } else {
            $result = blogCommentAdminReply($conn, (int) ($_POST['parent_id'] ?? 0), (string) ($_POST['reply_content'] ?? ''));
            $notice = $result['message'];
            $success = $result['ok'];
        }
    } else {
        $currentCustomer = customerCurrent($conn);
        $result = blogCommentCreate(
            $conn,
            (int) $post['id'],
            (string) ($_POST['author_name'] ?? ($currentCustomer['full_name'] ?? '')),
            (string) ($_POST['author_email'] ?? ($currentCustomer['email'] ?? '')),
            (string) ($_POST['comment_content'] ?? ''),
            $currentCustomer ? (int) $currentCustomer['id'] : null
        );
        $notice = $result['message'];
        $success = $result['ok'];
    }

    if ($notice !== '') {
        pageFlashSet('post', $notice, $success);
    }
    redirect(app_url('post', ['slug' => $slug]) . '#post-comments');
}

