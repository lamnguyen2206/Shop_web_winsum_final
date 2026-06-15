<?php
require_once __DIR__ . '/views/cart-store.php';
require_once __DIR__ . '/auth/admin-auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/repositories/product-repository.php';

$view = isset($_GET['view']) ? (string) $_GET['view'] : 'home';
$navMenuCategories = productGetNavMenuCategories($conn);
$navCatalogCategory = trim((string) ($_GET['category'] ?? ''));
$isCatalogView = $view === 'catalog' || $view === 'product';
$cartCount = cartCountItems();
$isAdmin = adminCurrent();
?><header class="site-header">
    <div class="topbar">NỘI THẤT VÀ CHIẾU SÁNG CAO CẤP</div>

    <div class="navbar container">
        <a class="brand" href="<?php echo e(app_url('home')); ?>">winsum home</a>

        <nav class="main-nav">
            <ul>
                <li><a class="<?php echo ($view === 'home') ? 'active' : ''; ?>" href="<?php echo e(app_url('home')); ?>">Trang chủ</a></li>
                <li>
                    <a class="<?php echo ($isCatalogView && $navCatalogCategory === '') ? 'active' : ''; ?>" href="<?php echo e(app_url('catalog')); ?>">Tất cả sản phẩm</a>
                </li>
                <?php if (!empty($navMenuCategories)): ?>
                <li class="nav-category-select-item">
                    <div class="nav-category-select-wrap">
                        <label class="visually-hidden" for="nav-category-select">Danh mục</label>
                        <select
                            id="nav-category-select"
                            class="nav-category-select"
                            data-nav-category-select
                            data-catalog-url="<?php echo e(app_url('catalog')); ?>"
                            aria-label="Chọn danh mục sản phẩm"
                        >
                            <option value="" selected>Danh mục</option>
                            <?php foreach ($navMenuCategories as $navCat): ?>
                                <option value="<?php echo e($navCat['slug']); ?>"><?php echo htmlspecialchars($navCat['nav_label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </li>
                <?php endif; ?>
                <li><a class="<?php echo ($view === 'blog' || $view === 'post') ? 'active' : ''; ?>" href="<?php echo e(app_url('blog')); ?>">Blog</a></li>
                <?php if (!$isAdmin): ?>
                <li><a class="<?php echo ($view === 'orders' || $view === 'order-detail' || $view === 'order-return') ? 'active' : ''; ?>" href="<?php echo e(app_url('orders')); ?>">Đơn hàng</a></li>
                <?php endif; ?>
                <?php if (empty($currentCustomer) && !$isAdmin): ?>
                    <li><a class="<?php echo ($view === 'account') ? 'active' : ''; ?>" href="<?php echo e(auth_login_url('account')); ?>">Tài khoản</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="nav-icons">
            <button type="button" class="icon-link" id="site-search-open" title="Tìm kiếm sản phẩm" aria-label="Tìm kiếm" aria-expanded="false" aria-controls="site-search-overlay">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="16.65" y1="16.65" x2="21" y2="21"></line></svg>
            </button>
            <div class="nav-account-wrap">
                <?php if ($isAdmin): ?>
                    <span class="nav-account-label">Admin</span>
                <?php elseif (!empty($currentCustomer)): ?>
                    <span class="nav-account-label"><?php echo htmlspecialchars($currentCustomer['full_name']); ?></span>
                <?php endif; ?>
                <button type="button" class="icon-link nav-account-trigger<?php echo (!empty($currentCustomer) || $isAdmin) ? ' nav-account-trigger--active' : ''; ?>" aria-label="Tài khoản" aria-expanded="false" aria-haspopup="true" aria-controls="nav-account-menu" id="nav-account-btn">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </button>
                <div class="nav-account-dropdown" id="nav-account-menu" role="menu" hidden>
                    <?php if ($isAdmin): ?>
                        <p class="nav-account-dropdown-head">Tài khoản quản trị</p>
                        <a role="menuitem" href="<?php echo e(app_url('admin-dashboard')); ?>">Trang quản trị</a>
                        <form method="post" action="" class="nav-account-logout">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="auth_action" value="logout">
                            <button type="submit" class="nav-account-btn">Đăng xuất</button>
                        </form>
                    <?php elseif (!empty($currentCustomer)): ?>
                        <a role="menuitem" href="<?php echo e(app_url('account')); ?>#profile-edit">Sửa thông tin</a>
                        <a role="menuitem" href="<?php echo e(app_url('orders')); ?>">Đơn hàng của tôi</a>
                        <form method="post" action="" class="nav-account-logout">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="auth_action" value="logout">
                            <button type="submit" class="nav-account-btn">Đăng xuất</button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="nav-account-btn" role="menuitem" data-open-auth="login">Đăng nhập</button>
                        <button type="button" class="nav-account-btn" role="menuitem" data-open-auth="register">Đăng ký</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$isAdmin): ?>
                <a class="icon-link" title="Giỏ hàng" href="<?php echo e(app_url('cart')); ?>" aria-label="Giỏ hàng">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="20" r="1.5"></circle><circle cx="18" cy="20" r="1.5"></circle><path d="M3 4h2l2.2 10.5a1 1 0 0 0 1 .8H19a1 1 0 0 0 1-.8L22 7H7"></path></svg>
                    <?php echo $cartCount > 0 ? '<em class="cart-badge">' . $cartCount . '</em>' : ''; ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php include __DIR__ . '/views/site-search.php'; ?>
</header>
