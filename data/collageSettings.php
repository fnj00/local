<?php
declare(strict_types=1);

header('Content-Type: application/json');

$eventId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
$baseDir = __DIR__ . '/../photos';

if ($eventId > 0) {
    $settingsFile = $baseDir . '/' . $eventId . '/collage_settings.json';
} else {
    $settingsFile = $baseDir . '/collage_settings.json';
}

if (!file_exists($settingsFile)) {
    echo json_encode([
        'opacity' => 0.40
    ]);
    exit;
}

echo file_get_contents($settingsFile);
