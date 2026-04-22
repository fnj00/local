<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (admin_is_logged_in()) {
    header('Location: /admin/events.php');
    exit;
}

$error = '';
$username = '';
$redirect = '/admin/events.php';

if (!empty($_GET['redirect'])) {
    $candidate = (string) $_GET['redirect'];
    if (strpos($candidate, '/admin/') === 0) {
        $redirect = $candidate;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $token = (string) ($_POST['csrf_token'] ?? '');
    $postedRedirect = (string) ($_POST['redirect'] ?? '/admin/events.php');

    if (strpos($postedRedirect, '/admin/') === 0) {
        $redirect = $postedRedirect;
    }

    if (!csrf_validate($token)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $stmt = $mysqli->prepare("
            SELECT id, username, password_hash, is_active
            FROM admin_users
            WHERE username = ?
            LIMIT 1
        ");

        if (!$stmt) {
            http_response_code(500);
            exit('Unable to prepare login query.');
        }

        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($user && (int) $user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
            admin_login($user);

            $updateStmt = $mysqli->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?");
            if ($updateStmt) {
                $userId = (int) $user['id'];
                $updateStmt->bind_param('i', $userId);
                $updateStmt->execute();
                $updateStmt->close();
            }

            header('Location: ' . $redirect);
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            padding: 40px 20px;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
        }
        .card {
            max-width: 420px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }
        h1 { margin-top: 0; }
        label {
            display: block;
            margin: 14px 0 6px;
            font-weight: 600;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #cfd6dd;
            border-radius: 8px;
        }
        .error {
            background: #fde7e9;
            color: #8a1f2d;
            border: 1px solid #f3c1c7;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        button {
            margin-top: 18px;
            width: 100%;
            padding: 12px;
            border: 0;
            border-radius: 8px;
            background: #1f5eff;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .muted {
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Admin Login</h1>
        <p class="muted">Use your local admin username and password.</p>

        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="/admin/login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">

            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" required value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button type="submit">Sign in</button>
        </form>
    </div>
</body>
</html>
