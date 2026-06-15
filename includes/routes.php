<?php

declare(strict_types=1);

/**
 * @return array{
 *   views: array<string,string>,
 *   titles: array<string,string>,
 *   default: string
 * }
 */
function appRoutes(): array
{
    $views = [
        'home' => 'includes/views/home.php',
        'catalog' => 'includes/views/catalog.php',
        'product' => 'includes/views/product-detail.php',
        'blog' => 'includes/views/blog.php',
        'post' => 'includes/views/blog-detail.php',
        'blog-editor' => 'includes/views/blog-editor.php',
        'cart' => 'includes/views/cart.php',
        'checkout' => 'includes/views/checkout.php',
        'account' => 'includes/views/account.php',
        'orders' => 'includes/views/my-orders.php',
        'order-lookup' => 'includes/views/order-lookup.php',
        'order-detail' => 'includes/views/order-detail.php',
        'order-return' => 'includes/views/order-return.php',
        'admin-dashboard' => 'includes/admin/admin-dashboard.php',
        'admin-orders' => 'includes/admin/admin-orders.php',
        'admin-order-detail' => 'includes/admin/admin-order-detail.php',
        'admin-customers' => 'includes/admin/admin-customers.php',
        'admin-products' => 'includes/admin/admin-products.php',
        'admin-product-create' => 'includes/admin/admin-product-form.php',
        'admin-product-edit' => 'includes/admin/admin-product-form.php',
        'admin-coupons' => 'includes/admin/admin-coupons.php',
        'admin-coupon-create' => 'includes/admin/admin-coupon-form.php',
        'admin-coupon-edit' => 'includes/admin/admin-coupon-form.php',
        'admin-reviews' => 'includes/admin/admin-reviews.php',
        'admin-returns' => 'includes/admin/admin-returns.php',
        'admin-blog' => 'includes/admin/admin-blog.php',
        'admin-blog-comments' => 'includes/admin/admin-blog-comments.php',
    ];

    $titles = [
        'catalog' => 'Sản phẩm | Winsum Home',
        'product' => 'Chi tiết sản phẩm | Winsum Home',
        'blog' => 'Tin tức | Winsum Home',
        'post' => 'Chi tiết bài viết | Winsum Home',
        'blog-editor' => 'Soạn bài blog | Winsum Home',
        'cart' => 'Giỏ hàng | Winsum Home',
        'checkout' => 'Thanh toán | Winsum Home',
        'account' => 'Tài khoản | Winsum Home',
        'orders' => 'Đơn hàng của tôi | Winsum Home',
        'order-lookup' => 'Tra cứu đơn hàng | Winsum Home',
        'order-detail' => 'Chi tiết đơn hàng | Winsum Home',
        'order-return' => 'Yêu cầu hoàn hàng | Winsum Home',
        'admin-dashboard' => 'Bảng quản trị | Winsum Home',
        'admin-orders' => 'Quản trị đơn hàng | Winsum Home',
        'admin-order-detail' => 'Chi tiết đơn hàng (quản trị) | Winsum Home',
        'admin-customers' => 'Quản lý khách hàng | Winsum Home',
        'admin-products' => 'Quản trị sản phẩm | Winsum Home',
        'admin-product-create' => 'Thêm sản phẩm | Winsum Home',
        'admin-product-edit' => 'Chỉnh sửa sản phẩm | Winsum Home',
        'admin-coupons' => 'Mã giảm giá | Winsum Home',
        'admin-coupon-create' => 'Tạo mã giảm giá | Winsum Home',
        'admin-coupon-edit' => 'Sửa mã giảm giá | Winsum Home',
        'admin-reviews' => 'Quản trị đánh giá | Winsum Home',
        'admin-returns' => 'Hoàn hàng / Khiếu nại | Winsum Home',
        'admin-blog' => 'Quản lý blog | Winsum Home',
        'admin-blog-comments' => 'Bình luận blog | Winsum Home',
    ];

    return [
        'views' => $views,
        'titles' => $titles,
        'default' => 'home',
        'default_title' => 'Winsum Home | Nội thất và chiếu sáng cao cấp',
    ];
}

function appResolveView(string $requested): array
{
    $routes = appRoutes();
    $view = $requested;
    if (!isset($routes['views'][$view])) {
        $view = $routes['default'];
        $is404 = $requested !== '' && $requested !== $routes['default'];
    } else {
        $is404 = false;
    }

    $pageTitle = $routes['titles'][$view] ?? $routes['default_title'];
    $includeFile = $routes['views'][$view];

    return [
        'view' => $view,
        'include_file' => $includeFile,
        'page_title' => $pageTitle,
        'is_404' => $is404,
    ];
}

/**
 * @return array{styles: list<string>, scripts: list<string>}
 */
function appAssetsForView(string $view, bool $storefrontGuest): array
{
    $styles = ['assets/css/site-search.css', 'assets/css/auth-toast.css'];
    $scripts = ['assets/js/auth-forms.js', 'assets/js/site-search.js', 'assets/js/auth-toast.js'];

    if ($view === 'product') {
        $styles[] = 'assets/css/product-detail.css';
        $scripts[] = 'assets/js/product-detail.js';
    }
    if ($view === 'checkout') {
        $scripts[] = 'assets/js/checkout.js';
    }
    if ($view === 'cart') {
        $couponJs = __DIR__ . '/../assets/js/coupon-suggestions.js';
        $scripts[] = 'assets/js/coupon-suggestions.js?v=' . (int) @filemtime($couponJs);
    }
    if ($view === 'home') {
        $scripts[] = 'assets/js/home.js';
    }
    if ($storefrontGuest) {
        $styles[] = 'assets/css/auth-forms.css';
    }
    if (str_starts_with($view, 'admin') || $view === 'blog-editor') {
        $styles[] = 'assets/css/admin.css';
    }
    if ($view === 'admin-dashboard') {
        $scripts[] = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
        $dashboardJs = __DIR__ . '/../assets/js/admin-dashboard.js';
        $scripts[] = 'assets/js/admin-dashboard.js?v=' . (int) @filemtime($dashboardJs);
    }
    if ($view === 'blog-editor') {
        $styles[] = 'assets/css/blog-editor.css';
        $scripts[] = 'assets/js/blog-editor.js';
    }
    if ($view === 'admin-customers') {
        $scripts[] = 'assets/js/admin-customers.js';
    }

    return ['styles' => $styles, 'scripts' => $scripts];
}
