<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('local_admin');
    session_start();
}

const ADMIN_SESSION_TIMEOUT = 60 * 60 * 8; // 8 hours

if (isset($_SESSION['admin_last_activity'])) {
    if ((time() - (int)$_SESSION['admin_last_activity']) > ADMIN_SESSION_TIMEOUT) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
}

$_SESSION['admin_last_activity'] = time();
