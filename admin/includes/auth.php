<?php
declare(strict_types=1);

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_user_id']);
}

function require_admin(): void
{
    if (!admin_is_logged_in()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/admin/');
        header('Location: /admin/login.php?redirect=' . $redirect);
        exit;
    }
}

function admin_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int)$user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['admin_last_activity'] = time();
}

function admin_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
