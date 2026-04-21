<?php
session_start();

$error = '';

$adminUser = 'joedeejay';
$adminPass = 'j0erulzall';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === $adminUser && $password === $adminPass) {
        session_regenerate_id(true);
        $_SESSION['collage_admin_logged_in'] = true;
	error_log('LOGIN OK: session id=' . session_id());

        $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '/collagefix/admin.php';

        // only allow local relative redirects
        if (strpos($redirect, '/collagefix/') !== 0) {
            $redirect = '/collagefix/admin.php';
        }

        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Collage Admin Login</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #111;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .login-box {
            background: #1d1d1d;
            padding: 24px;
            border-radius: 10px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 0 14px rgba(0,0,0,0.35);
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 8px 0 16px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            cursor: pointer;
        }

        .error {
            color: #ff6b6b;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Collage Admin Login</h1>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post">
            <label>Username</label>
            <input type="text" name="username" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">Log In</button>
        </form>
    </div>
</body>
</html>
