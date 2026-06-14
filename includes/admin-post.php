<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Xử lý POST trang quản trị trước khi xuất HTML (tránh lỗi headers already sent).
 */
function adminHandlePost(mysqli $conn, string $view): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    require_once __DIR__ . '/csrf.php';
    require_once __DIR__ . '/customer-auth.php';
    require_once __DIR__ . '/admin-auth.php';

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'admin_logout') {
        if (!csrfValidate()) {
            return;
        }
        customerLogout();
        $_SESSION['auth_flash'] = [
            'message' => 'Bạn đã đăng xuất.',
            'success' => true,
            'open' => null,
            'prefill' => [],
        ];
        redirect(app_url('home'));
    }

    if (!adminCurrent()) {
        return;
    }

    $customerAdminActions = ['toggle_customer_block', 'update_customer_status'];
    if (in_array($action, $customerAdminActions, true)) {
        adminHandleCustomersPost($conn);
        return;
    }

    if ($view === 'admin-customers') {
        adminHandleCustomersPost($conn);
        return;
    }

    if (in_array($view, ['admin-products', 'admin-product-create', 'admin-product-edit'], true) && csrfValidate()) {
        adminHandleProductsPost($conn);
    }

    if (in_array($view, ['admin-coupons', 'admin-coupon-create', 'admin-coupon-edit'], true) && csrfValidate()) {
        adminHandleCouponsPost($conn);
    }

    if (in_array($view, ['admin-orders', 'admin-order-detail'], true) && csrfValidate()) {
        adminHandleOrdersPost($conn, $view);
    }

    if ($view === 'admin-returns' && csrfValidate()) {
        adminHandleReturnsPost($conn);
    }

    if ($view === 'admin-blog' && csrfValidate()) {
        adminHandleBlogPost($conn);
    }
}

function adminHandleReturnsPost(mysqli $conn): void
{
    require_once __DIR__ . '/return-repository.php';

    $action = (string) ($_POST['action'] ?? '');
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $adminNote = trim((string) ($_POST['admin_note'] ?? ''));

    if ($requestId <= 0) {
        redirect(app_url('admin-returns', ['msg' => 'Yêu cầu không hợp lệ.', 'msg_ok' => '0']));
    }

    $redirectStatus = 'pending';

    if ($action === 'return_accept' || $action === 'return_approve') {
        $result = returnAdminAccept($conn, $requestId, $adminNote);
        $redirectStatus = 'pending';
    } elseif ($action === 'return_reject') {
        $result = returnAdminReject($conn, $requestId, $adminNote);
        $redirectStatus = 'pending';
    } elseif ($action === 'return_confirm_goods') {
        $shippingCost = (float) ($_POST['return_shipping_cost'] ?? 0);
        $result = returnAdminConfirmGoodsReceived($conn, $requestId, $shippingCost);
        $redirectStatus = 'accepted';
    } elseif ($action === 'return_complete_refund') {
        $result = returnAdminCompleteRefund($conn, $requestId, $adminNote);
        $redirectStatus = $result['ok'] ? 'completed' : 'goods_received';
    } else {
        redirect(app_url('admin-returns'));
    }

    redirect(app_url('admin-returns', [
        'msg' => $result['message'],
        'msg_ok' => $result['ok'] ? '1' : '0',
        'status' => $redirectStatus,
    ]));
}

function adminHandleBlogPost(mysqli $conn): void
{
    require_once __DIR__ . '/blog-repository.php';

    $action = (string) ($_POST['action'] ?? '');
    $postId = (int) ($_POST['post_id'] ?? 0);
    $message = 'Thao tác không hợp lệ.';
    $ok = false;

    if ($postId <= 0) {
        redirect(app_url('admin-blog', ['msg' => $message]));
    }

    if ($action === 'delete_blog_post') {
        $ok = blogAdminDelete($conn, $postId);
        $message = $ok ? 'Đã xóa bài viết.' : 'Không thể xóa bài viết.';
    } elseif ($action === 'toggle_blog_featured') {
        $ok = blogAdminToggleFeatured($conn, $postId);
        $message = $ok ? 'Đã cập nhật bài nổi bật.' : 'Không thể cập nhật.';
    } elseif ($action === 'set_blog_status') {
        $status = (string) ($_POST['status'] ?? '');
        $ok = blogAdminSetStatus($conn, $postId, $status);
        $message = $ok
            ? 'Đã cập nhật trạng thái: ' . blogStatusLabel($status) . '.'
            : 'Không thể cập nhật trạng thái.';
    }

    redirect(app_url('admin-blog', ['msg' => $message]));
}

function adminHandleOrdersPost(mysqli $conn, string $view): void
{
    require_once __DIR__ . '/order-repository.php';

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_payment_status') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $paymentStatus = (string) ($_POST['payment_status'] ?? '');
        $ok = orderUpdatePaymentStatus($conn, $orderId, $paymentStatus);
        $stmt = $conn->prepare('SELECT order_code FROM orders WHERE id = ? LIMIT 1');
        $code = '';
        if ($stmt) {
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $code = (string) ($row['order_code'] ?? '');
            $stmt->close();
        }
        redirect(app_url('admin-order-detail', [
            'code' => $code,
            'msg' => $ok ? 'Đã cập nhật trạng thái thanh toán.' : 'Không thể cập nhật thanh toán. Chỉ đơn COD đã giao mới được chỉnh thủ công.',
            'msg_ok' => $ok ? '1' : '0',
        ]));
    }

    if ($action === 'update_fulfillment_status') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $fulfillment = (string) ($_POST['fulfillment_status'] ?? '');
        $ok = orderUpdateFulfillmentStatus($conn, $orderId, $fulfillment);
        $stmt = $conn->prepare('SELECT order_code FROM orders WHERE id = ? LIMIT 1');
        $code = '';
        if ($stmt) {
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $code = (string) ($row['order_code'] ?? '');
            $stmt->close();
        }
        redirect(app_url('admin-order-detail', [
            'code' => $code,
            'msg' => $ok ? 'Đã cập nhật trạng thái giao hàng.' : 'Không thể cập nhật giao hàng.',
            'msg_ok' => $ok ? '1' : '0',
        ]));
    }
}

function adminHandleCustomersPost(mysqli $conn): void
{
    require_once __DIR__ . '/customer-admin-repository.php';

    if (!csrfValidate()) {
        redirect(app_url('admin-customers', ['msg' => 'Phiên làm việc không hợp lệ.']));
    }

    $filters = customerAdminParseFilters(array_merge($_GET, $_POST));
    $perPage = 20;
    $total = customerAdminCount($conn, $filters);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($filters['page'], $totalPages);
    $detailId = (int) ($_GET['id'] ?? 0);
    $actingId = adminManagementActingCustomerId();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_customer_status') {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        $result = customerAdminUpdateStatus($conn, $customerId, $newStatus, $actingId);
        if ($result['ok']) {
            $redirect = customerAdminBuildListUrl($filters, $page) . '&id=' . $customerId;
            redirect($redirect . '&msg=' . urlencode($result['message']) . '&msg_ok=1');
        }
        redirect(customerAdminBuildListUrl($filters, $page) . '&msg=' . urlencode($result['message']) . '&msg_ok=0');
    }

    if ($action === 'toggle_customer_block') {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $result = customerAdminToggleBlock($conn, $customerId, $actingId);
        if ($result['ok']) {
            $redirect = customerAdminBuildListUrl($filters, $page);
            if ($detailId === $customerId || $customerId > 0) {
                $redirect .= '&id=' . $customerId;
            }
            redirect($redirect . '&msg=' . urlencode($result['message']) . '&msg_ok=1');
        }
        redirect(customerAdminBuildListUrl($filters, $page) . '&msg=' . urlencode($result['message']) . '&msg_ok=0');
    }

    redirect(customerAdminBuildListUrl($filters, $page) . '&msg=' . urlencode('Thao tác không hợp lệ.') . '&msg_ok=0');
}

function adminHandleCouponsPost(mysqli $conn): void
{
    require_once __DIR__ . '/coupon-admin-repository.php';

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_coupon') {
        $result = couponAdminSave($conn, $_POST);
        if (!$result['ok']) {
            $editId = (int) ($_POST['id'] ?? 0);
            $params = ['msg' => $result['message'], 'msg_ok' => '0'];
            if ($editId > 0) {
                redirect(app_url('admin-coupon-edit', array_merge($params, ['id' => $editId])));
            }
            redirect(app_url('admin-coupon-create', $params));
        }
        $couponId = (int) ($result['coupon_id'] ?? ($_POST['id'] ?? 0));
        redirect(app_url('admin-coupon-edit', [
            'id' => $couponId,
            'msg' => $result['message'],
            'msg_ok' => '1',
        ]));
    }

    if ($action === 'toggle_coupon_active') {
        $couponId = (int) ($_POST['coupon_id'] ?? 0);
        if ($couponId > 0 && couponAdminToggleActive($conn, $couponId)) {
            redirect(app_url('admin-coupons', ['msg' => 'Đã cập nhật trạng thái mã giảm giá.', 'msg_ok' => '1']));
        }
        redirect(app_url('admin-coupons', ['msg' => 'Không thể cập nhật mã giảm giá.', 'msg_ok' => '0']));
    }
}

function adminHandleProductsPost(mysqli $conn): void
{
    require_once __DIR__ . '/product-admin-repository.php';
    require_once __DIR__ . '/inventory-repository.php';

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_product') {
        $result = productAdminSave($conn, $_POST);
        $msg = $result['message'];
        if (!$result['ok']) {
            $params = ['msg' => $msg, 'msg_ok' => '0'];
            $editId = (int) ($_POST['id'] ?? 0);
            if ($editId > 0) {
                $params['id'] = $editId;
                redirect(app_url('admin-product-edit', $params));
            }
            redirect(app_url('admin-product-create', $params));
        }
        $productId = (int) ($result['product_id'] ?? ($_POST['id'] ?? 0));
        redirect(app_url('admin-product-edit', [
            'id' => $productId,
            'msg' => $msg,
            'msg_ok' => '1',
        ]));
    }

    if ($action === 'delete_product') {
        $deleteId = (int) ($_POST['product_id'] ?? 0);
        if (productAdminDelete($conn, $deleteId)) {
            redirect(app_url('admin-products', ['msg' => 'Đã ẩn sản phẩm khỏi cửa hàng.', 'msg_ok' => '1']));
        }
        redirect(app_url('admin-products', ['msg' => 'Không thể ẩn sản phẩm.', 'msg_ok' => '0']));
    }

    if ($action === 'mark_inventory_read') {
        $alertId = (int) ($_POST['alert_id'] ?? 0);
        if ($alertId > 0 && inventoryMarkAlertRead($conn, $alertId)) {
            redirect(app_url('admin-products', ['msg' => 'Đã đánh dấu đã xử lý cảnh báo tồn kho.', 'msg_ok' => '1']));
        }
        redirect(app_url('admin-products', ['msg' => 'Không thể cập nhật cảnh báo tồn kho.', 'msg_ok' => '0']));
    }

    if ($action === 'mark_all_inventory_read') {
        inventoryMarkAllAlertsRead($conn);
        redirect(app_url('admin-products', ['msg' => 'Đã đánh dấu tất cả cảnh báo tồn kho.', 'msg_ok' => '1']));
    }

}
