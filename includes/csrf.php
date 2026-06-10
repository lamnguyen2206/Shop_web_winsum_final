<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    $token = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function csrfValidate(): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    return $sessionToken !== '' && hash_equals($sessionToken, $postedToken);
}

function csrfRequire(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    return csrfValidate();
}
