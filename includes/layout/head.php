<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/catalog.css">
    <link rel="stylesheet" href="assets/css/blog.css">
    <link rel="stylesheet" href="assets/css/cart.css?v=<?php echo (int) @filemtime(__DIR__ . '/../../assets/css/cart.css'); ?>">
    <link rel="stylesheet" href="assets/css/account.css">
    <?php foreach ($extraStyles as $styleHref): ?>
        <link rel="stylesheet" href="<?php echo e($styleHref); ?>">
    <?php endforeach; ?>
</head>
<body<?php
$bodyAttr = [];
if ($storefrontGuest) {
    $bodyAttr[] = 'class="has-storefront-auth-ui"';
}
if ($authOpenModal && $storefrontGuest) {
    $bodyAttr[] = 'data-auth-open="' . e($authOpenModal) . '"';
}
echo $bodyAttr !== [] ? ' ' . implode(' ', $bodyAttr) : '';
?>>
