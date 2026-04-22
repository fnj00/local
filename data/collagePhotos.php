<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function file_url_for_photo(int $eventId, string $filename, string $suffix = '', bool $preferWebp = false): ?string
{
    $baseFs = __DIR__ . '/../photos/' . $eventId . '/' . $filename;
    $baseUrl = '/photos/' . $eventId . '/' . rawurlencode($filename);

    $candidates = [];

    if ($preferWebp) {
        $candidates[] = [$baseFs . $suffix . '.webp', $baseUrl . $suffix . '.webp'];
    }

    $candidates[] = [$baseFs . $suffix . '.jpg', $baseUrl . $suffix . '.jpg'];
    $candidates[] = [$baseFs . $suffix . '.png', $baseUrl . $suffix . '.png'];

    if (!$preferWebp) {
        $candidates[] = [$baseFs . $suffix . '.webp', $baseUrl . $suffix . '.webp'];
    }

    foreach ($candidates as [$fs, $url]) {
        if (is_file($fs)) {
            return $url;
        }
    }

    return null;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    json_response([
        'success' => false,
        'message' => 'Missing or invalid event id.'
    ], 400);
}

$eventId = (int) $_GET['id'];

$stmt = $mysqli->prepare("
    SELECT ID, filename, photonum
    FROM photos
    WHERE eventId = ?
      AND approved = 1
      AND photonum IS NOT NULL
      AND photonum > 0
    ORDER BY photonum ASC, ID ASC
");

if (!$stmt) {
    json_response([
        'success' => false,
        'message' => 'Unable to prepare photo query.'
    ], 500);
}

$stmt->bind_param('i', $eventId);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];

while ($result && ($row = $result->fetch_assoc())) {
    $filename = (string) $row['filename'];

    $thumbUrl = file_url_for_photo($eventId, $filename, '_thumb', true);
    $fullUrl = file_url_for_photo($eventId, $filename, '', false);

    if ($fullUrl === null) {
        continue;
    }

    if ($thumbUrl === null) {
        $thumbUrl = $fullUrl;
    }

    $rows[] = [
        'id' => (int) $row['ID'],
        'eventId' => $eventId,
        'filename' => $filename,
        'photonum' => isset($row['photonum']) ? (int) $row['photonum'] : 0,
        'thumbUrl' => $thumbUrl,
        'fullUrl' => $fullUrl,
        'image' => $thumbUrl
    ];
}

$stmt->close();

json_response($rows);
