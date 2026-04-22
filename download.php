<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

function fail_download(int $statusCode = 404, string $message = 'File not found.'): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$numRaw = $_GET['num'] ?? null;
$fileRaw = $_GET['file'] ?? null;

if ($numRaw === null || $fileRaw === null) {
    fail_download(400, 'Missing required parameters.');
}

if (!is_numeric($numRaw)) {
    fail_download(400, 'Invalid event ID.');
}

$eventId = (int) $numRaw;
if ($eventId <= 0) {
    fail_download(400, 'Invalid event ID.');
}

/*
 * Only allow a simple stored base filename.
 * This blocks path traversal and prevents callers from directly requesting
 * extensions or thumbnail variants.
 */
$baseName = pathinfo((string) $fileRaw, PATHINFO_FILENAME);
$baseName = preg_replace('/[^A-Za-z0-9._-]/', '', $baseName ?? '');

if ($baseName === '') {
    fail_download(400, 'Invalid file name.');
}

/* Never allow thumbnail downloads from this endpoint */
if (preg_match('/_thumb$/i', $baseName)) {
    fail_download(400, 'Thumbnail downloads are not allowed from this endpoint.');
}

/*
 * Optional DB existence check:
 * Only allow files that exist in the photos table for the given event.
 */
$stmt = $mysqli->prepare("
    SELECT filename
    FROM photos
    WHERE eventId = ? AND filename = ?
    LIMIT 1
");

if (!$stmt) {
    fail_download(500, 'Unable to verify file.');
}

$stmt->bind_param('is', $eventId, $baseName);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    fail_download(404, 'Photo record not found.');
}

$photoDir = __DIR__ . '/photos/' . $eventId;
if (!is_dir($photoDir)) {
    fail_download(404, 'Photo directory not found.');
}

/*
 * Prefer the full-size JPG, then PNG, then WEBP.
 * Do not include thumbnail paths here.
 */
$candidates = [
    [
        'path' => $photoDir . '/' . $baseName . '.jpg',
        'download_name' => $baseName . '.jpg',
        'content_type' => 'image/jpeg',
    ],
    [
        'path' => $photoDir . '/' . $baseName . '.png',
        'download_name' => $baseName . '.png',
        'content_type' => 'image/png',
    ],
    [
        'path' => $photoDir . '/' . $baseName . '.webp',
        'download_name' => $baseName . '.webp',
        'content_type' => 'image/webp',
    ],
];

$selected = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate['path'])) {
        $selected = $candidate;
        break;
    }
}

if ($selected === null) {
    fail_download(404, 'Full-size image not found.');
}

$filePath = $selected['path'];
$downloadName = $selected['download_name'];
$contentType = $selected['content_type'];
$fileSize = filesize($filePath);

if ($fileSize === false) {
    fail_download(500, 'Unable to read file size.');
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('Content-Length: ' . $fileSize);
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, no-transform, no-store, must-revalidate');
header('Pragma: public');
header('Expires: 0');

$handle = fopen($filePath, 'rb');
if ($handle === false) {
    fail_download(500, 'Unable to open file.');
}

while (!feof($handle)) {
    $buffer = fread($handle, 8192);
    if ($buffer === false) {
        fclose($handle);
        fail_download(500, 'Unable to read file.');
    }
    echo $buffer;
}

fclose($handle);
exit;
