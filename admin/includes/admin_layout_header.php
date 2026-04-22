<?php
declare(strict_types=1);

if (!isset($pageTitle)) {
    $pageTitle = 'Admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #f4f6f8;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #dbe3ea;
            --primary: #1f5eff;
            --danger: #c62828;
            --success-bg: #e8f5e9;
            --success-text: #256029;
            --warn-bg: #fff3e0;
            --warn-text: #8a5200;
            --error-bg: #fde7e9;
            --error-text: #8a1f2d;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .topbar {
            background: #111827;
            color: #fff;
            padding: 14px 20px;
        }

        .topbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .brand {
            font-size: 20px;
            font-weight: 700;
        }

        .nav {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .nav a {
            color: #fff;
            text-decoration: none;
            padding: 8px 10px;
            border-radius: 6px;
        }

        .nav a:hover,
        .nav a.active {
            background: rgba(255,255,255,.12);
        }

        .container {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 16px 32px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 8px 20px rgba(0,0,0,.04);
            margin-bottom: 18px;
        }

        .grid {
            display: grid;
            gap: 16px;
        }

        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        .row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="password"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
            font: inherit;
            background: #fff;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            display: inline-block;
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            background: var(--primary);
            color: #fff;
        }

        .btn-secondary {
            background: #374151;
        }

        .btn-danger {
            background: var(--danger);
        }

        .btn-link {
            background: transparent;
            color: var(--primary);
            padding: 0;
            font-weight: 600;
        }

        .message {
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 16px;
        }

        .message-success {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .message-warning {
            background: var(--warn-bg);
            color: var(--warn-text);
        }

        .message-error {
            background: var(--error-bg);
            color: var(--error-text);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 10px 8px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        .muted {
            color: var(--muted);
        }

        .pill {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 12px;
            font-weight: 700;
            margin-right: 6px;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-inner">
            <div class="brand">Site Admin</div>
            <div class="nav">
                <a href="/admin/events.php">Events</a>
                <a href="/admin/photos.php">Photos</a>
                <a href="/admin/collage.php">Collage</a>
                <a href="/admin/bulk_upload.php">Bulk Upload</a>
		<a href="/admin/logout.php">Logout</a>
            </div>
        </div>
    </div>
    <div class="container">
