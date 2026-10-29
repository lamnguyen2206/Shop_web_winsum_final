<?php

declare(strict_types=1);

function storefrontHandleCatalogPost(mysqli $conn): void
{
    if (($_POST['action'] ?? '') !== 'add_to_cart') {
        return;
    }

    require_once __DIR__ . '/../product-repository.php';
    require_once __DIR__ . '/../cart-store.php';
    require_once __DIR__ . '/../customer-auth.php';

    $notice = '';
    $success = false;
    $params = array_diff_key($_GET, ['view' => true]);

    if (!csrfValidate()) {
        $notice = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $catalogCustomer = customerCurrent($conn);
        if (!storefrontMayAddToCart($catalogCustomer)) {
            $notice = 'Tài khoản quản trị không thể thêm sản phẩm vào giỏ hàng.';
        } else {
            $productId = (int) ($_POST['product_id'] ?? 0);
            $qty = max(1, (int) ($_POST['qty'] ?? 1));
            $product = productGetById($conn, $productId);
            if (!$product) {
                $notice = 'Không tìm thấy sản phẩm.';
            } else {
                $cartQty = 0;
                foreach (cartGetItems() as $cartLine) {
                    if ((int) ($cartLine['product_id'] ?? 0) === (int) $product['id']) {
                        $cartQty += (int) ($cartLine['qty'] ?? 0);
                    }
                }
                $stockCheck = inventoryValidatePurchase($conn, (int) $product['id'], $product['stock_status'], $cartQty + $qty);
                if ($stockCheck['ok']) {
                    cartAddItem([
                        'id' => 'product-' . $product['id'],
                        'product_id' => $product['id'],
                        'slug' => $product['slug'],
                        'name' => $product['name'],
                        'sku' => $product['sku'],
                        'price' => $product['price'],
                        'image' => $product['image'],
                    ], $qty);
                    $notice = 'Đã thêm sản phẩm vào giỏ hàng.';
                    $success = true;
                } else {
                    $notice = $stockCheck['message'];
                }
            }
        }
    }

    pageFlashSet('catalog', $notice, $success);
    redirect(app_url('catalog', $params));
}


function storefrontHandleProductPost(mysqli $conn): void
{
    require_once __DIR__ . '/../product-repository.php';
    require_once __DIR__ . '/../cart-store.php';
    require_once __DIR__ . '/../review-repository.php';
    require_once __DIR__ . '/../customer-auth.php';

    $slug = trim((string) ($_GET['slug'] ?? ''));
    $product = $slug !== '' ? productGetBySlug($conn, $slug) : null;
    if (!$product) {
        return;
    }

    $action = (string) ($_POST['action'] ?? '');
    $notice = '';
    $success = false;

    if (!csrfValidate()) {
        $notice = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } elseif ($action === 'add_to_cart') {
        if (!storefrontMayAddToCart(customerCurrent($conn))) {
            $notice = 'Tài khoản quản trị không thể mua qua website.';
        } else {
            $qty = max(1, (int) ($_POST['qty'] ?? 1));
            $cartQty = 0;
            foreach (cartGetItems() as $cartLine) {
                if ((int) ($cartLine['product_id'] ?? 0) === (int) $product['id']) {
                    $cartQty += (int) ($cartLine['qty'] ?? 0);
                }
            }
            $stockCheck = inventoryValidatePurchase($conn, (int) $product['id'], $product['stock_status'], $cartQty + $qty);
            if (!$stockCheck['ok']) {
                $notice = $stockCheck['message'];
            } else {
                cartAddItem([
                    'id' => 'product-' . $product['id'],
                    'product_id' => $product['id'],
                    'slug' => $product['slug'],
                    'name' => $product['name'],
                    'sku' => $product['sku'],
                    'price' => (int) round($product['base_price']),
                    'image' => $product['images'][0]['url'] ?? 'assets/images/blog_1.png',
                ], $qty);
                $notice = 'Đã thêm sản phẩm vào giỏ hàng.';
                $success = true;
            }
        }
    } elseif ($action === 'submit_review') {
        $currentCustomer = customerCurrent($conn);
        $result = reviewCreate(
            $conn,
            (int) $product['id'],
            (string) ($_POST['reviewer_name'] ?? ($currentCustomer['full_name'] ?? '')),
            (string) ($_POST['reviewer_email'] ?? ($currentCustomer['email'] ?? '')),
            (int) ($_POST['rating'] ?? 5),
            (string) ($_POST['review_title'] ?? ''),
            (string) ($_POST['review_content'] ?? ''),
            $currentCustomer ? (int) $currentCustomer['id'] : null
        );
        $notice = $result['message'];
        $success = $result['ok'];
    } elseif ($action === 'admin_review_reply') {
        if (!adminCurrent()) {
            $notice = 'Bạn cần đăng nhập quản trị để trả lời.';
        } else {
            $result = reviewAdminReply($conn, (int) ($_POST['parent_id'] ?? 0), (string) ($_POST['reply_content'] ?? ''));
            $notice = $result['message'];
            $success = $result['ok'];
        }
    }

    if ($notice !== '') {
        pageFlashSet('product', $notice, $success);
    }
    $hash = in_array($action, ['submit_review', 'admin_review_reply'], true) ? '#product-reviews' : '';
    redirect(app_url('product', ['slug' => $slug]) . $hash);
}

