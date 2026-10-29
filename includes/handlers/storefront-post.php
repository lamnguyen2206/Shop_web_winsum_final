<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../flash.php';
require_once __DIR__ . '/blog-comment-post.php';
require_once __DIR__ . '/order-post.php';
require_once __DIR__ . '/cart-post.php';
require_once __DIR__ . '/catalog-product-post.php';
require_once __DIR__ . '/checkout-post.php';
require_once __DIR__ . '/account-post.php';

function storefrontHandlePost(mysqli $conn, string $view): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if ($view === 'cart') {
        storefrontHandleCartPost($conn);
        return;
    }
    if ($view === 'catalog') {
        storefrontHandleCatalogPost($conn);
        return;
    }
    if ($view === 'product') {
        storefrontHandleProductPost($conn);
        return;
    }
    if ($view === 'checkout') {
        storefrontHandleCheckoutPost($conn);
        return;
    }
    if ($view === 'account') {
        storefrontHandleAccountPost($conn);
        return;
    }
    if ($view === 'order-detail') {
        storefrontHandleOrderDetailPost($conn);
        return;
    }
    if ($view === 'order-return') {
        storefrontHandleOrderReturnPost($conn);
        return;
    }
    if ($view === 'order-lookup') {
        storefrontHandleOrderLookupPost($conn);
        return;
    }
    if ($view === 'post') {
        storefrontHandleBlogPostPost($conn);
    }
}
