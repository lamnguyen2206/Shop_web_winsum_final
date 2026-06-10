<?php

declare(strict_types=1);

/**
 * Flash message theo trang (PRG) — key thường là tên view: cart, catalog, checkout...
 *
 * @return array{message:string,success:bool}
 */
function pageFlashConsume(string $key): array
{
    $empty = ['message' => '', 'success' => false];
    if (empty($_SESSION['page_flash'][$key]) || !is_array($_SESSION['page_flash'][$key])) {
        return $empty;
    }
    $f = $_SESSION['page_flash'][$key];
    unset($_SESSION['page_flash'][$key]);
    return [
        'message' => (string) ($f['message'] ?? ''),
        'success' => (bool) ($f['success'] ?? false),
    ];
}

function pageFlashSet(string $key, string $message, bool $success = false): void
{
    if (!isset($_SESSION['page_flash']) || !is_array($_SESSION['page_flash'])) {
        $_SESSION['page_flash'] = [];
    }
    $_SESSION['page_flash'][$key] = [
        'message' => $message,
        'success' => $success,
    ];
}
