<?php
session_start();

if (empty($_SESSION['collage_admin_logged_in'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(array('error' => 'Unauthorized'));
    exit;
}
header('Content-Type: application/json');

$settingsFile = __DIR__ . '/settings.json';

if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode([
        'opacity' => 0.40
    ], JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opacity = isset($_POST['opacity']) ? floatval($_POST['opacity']) : 0.40;
    $opacity = max(0, min(1, $opacity));

    $settings = [
        'opacity' => $opacity
    ];

    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'opacity' => $opacity]);
    exit;
}

echo file_get_contents($settingsFile);
