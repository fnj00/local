<?php
session_start();
error_log('AUTH CHECK: session id=' . session_id() . ' logged_in=' . (empty($_SESSION['collage_admin_logged_in']) ? 'no' : 'yes'));

if (empty($_SESSION['collage_admin_logged_in'])) {
    $redirectTarget = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/collagefix/admin.php';
    header('Location: /collagefix/login.php?redirect=' . urlencode($redirectTarget));
    exit;
}
