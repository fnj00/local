<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_admin();

function fail_export(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function detect_existing_image_path(string $basePathWithoutExt): ?string
{
    $candidates = [
        $basePathWithoutExt . '.jpg',
        $basePathWithoutExt . '.png',
        $basePathWithoutExt . '.webp',
        $basePathWithoutExt . '.gif',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function image_from_path(string $path)
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            return @imagecreatefromjpeg($path);
        case 'png':
            return @imagecreatefrompng($path);
        case 'webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
        case 'gif':
            return @imagecreatefromgif($path);
        default:
            return false;
    }
}

function save_output_image($image, string $mode, string $downloadName): void
{
    header('Content-Type: image/jpeg');
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Cache-Control: private, no-store, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    imagejpeg($image, null, 92);
    imagedestroy($image);
    exit;
}

function photo_source_path(int $eventId, string $filename): ?string
{
    $base = __DIR__ . '/../photos/' . $eventId . '/' . $filename;
    return detect_existing_image_path($base);
}

$eventId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
$mode = isset($_GET['mode']) ? strtolower(trim((string) $_GET['mode'])) : 'original';

if ($eventId <= 0) {
    fail_export(400, 'Missing or invalid event id.');
}

if (!in_array($mode, ['original', 'tilefull'], true)) {
    fail_export(400, 'Invalid export mode.');
}

$stmt = $mysqli->prepare("
    SELECT ID, title, collagex, collagey, numcollage, collageimg, imgWidth, imgHeight
    FROM events
    WHERE ID = ?
    LIMIT 1
");

if (!$stmt) {
    fail_export(500, 'Unable to prepare event query.');
}

$stmt->bind_param('i', $eventId);
$stmt->execute();
$result = $stmt->get_result();
$event = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$event) {
    fail_export(404, 'Event not found.');
}

$collageX = max(1, (int) ($event['collagex'] ?? 1));
$collageY = max(1, (int) ($event['collagey'] ?? 1));
$numCollage = max(1, (int) ($event['numcollage'] ?? ($collageX * $collageY)));
$imgWidth = max(1, (int) ($event['imgWidth'] ?? 1));
$imgHeight = max(1, (int) ($event['imgHeight'] ?? 1));
$gap = 2;

$backgroundPath = null;
if (!empty($event['collageimg'])) {
    $backgroundPath = __DIR__ . '/../photos/' . $eventId . '/' . $event['collageimg'];
    if (!is_file($backgroundPath)) {
        $backgroundPath = null;
    }
}

if ($backgroundPath === null) {
    foreach ([
        __DIR__ . '/../photos/' . $eventId . '/collage.jpg',
        __DIR__ . '/../photos/' . $eventId . '/collage.png',
        __DIR__ . '/../photos/' . $eventId . '/collage.webp',
        __DIR__ . '/../photos/' . $eventId . '/background.jpg',
        __DIR__ . '/../photos/' . $eventId . '/background.png',
        __DIR__ . '/../photos/' . $eventId . '/background.webp',
    ] as $candidate) {
        if (is_file($candidate)) {
            $backgroundPath = $candidate;
            break;
        }
    }
}

if ($backgroundPath === null) {
    fail_export(404, 'Collage background image not found.');
}

$background = image_from_path($backgroundPath);
if (!$background) {
    fail_export(500, 'Unable to open collage background image.');
}

$backgroundWidth = imagesx($background);
$backgroundHeight = imagesy($background);

if ($backgroundWidth > 0 && $backgroundHeight > 0) {
    $imgWidth = $backgroundWidth;
    $imgHeight = $backgroundHeight;
}

$canvas = imagecreatetruecolor($imgWidth, $imgHeight);
if (!$canvas) {
    imagedestroy($background);
    fail_export(500, 'Unable to create export canvas.');
}

$white = imagecolorallocate($canvas, 255, 255, 255);
imagefill($canvas, 0, 0, $white);
imagecopy($canvas, $background, 0, 0, 0, 0, $imgWidth, $imgHeight);
imagedestroy($background);

$stmt = $mysqli->prepare("
    SELECT filename, photonum
    FROM photos
    WHERE eventId = ?
      AND approved = 1
      AND photonum IS NOT NULL
      AND photonum > 0
    ORDER BY photonum ASC
");

if (!$stmt) {
    imagedestroy($canvas);
    fail_export(500, 'Unable to prepare photos query.');
}

$stmt->bind_param('i', $eventId);
$stmt->execute();
$result = $stmt->get_result();

$photosByTile = [];
while ($result && ($row = $result->fetch_assoc())) {
    $tile = (int) $row['photonum'];
    if ($tile > 0 && $tile <= $numCollage) {
        $photosByTile[$tile] = (string) $row['filename'];
    }
}
$stmt->close();

$tileWidthFloat = ($imgWidth - (($collageX - 1) * $gap)) / $collageX;
$tileHeightFloat = ($imgHeight - (($collageY - 1) * $gap)) / $collageY;

for ($tile = 1; $tile <= $numCollage; $tile++) {
    if (!isset($photosByTile[$tile])) {
        continue;
    }

    $index = $tile - 1;
    $col = $index % $collageX;
    $row = (int) floor($index / $collageX);

    $left = (int) round($col * ($tileWidthFloat + $gap));
    $top = (int) round($row * ($tileHeightFloat + $gap));

    if ($col === $collageX - 1) {
        $tileWidth = $imgWidth - $left;
    } else {
        $nextLeft = (int) round(($col + 1) * ($tileWidthFloat + $gap));
        $tileWidth = $nextLeft - $left - $gap;
    }

    if ($row === $collageY - 1) {
        $tileHeight = $imgHeight - $top;
    } else {
        $nextTop = (int) round(($row + 1) * ($tileHeightFloat + $gap));
        $tileHeight = $nextTop - $top - $gap;
    }

    $photoPath = photo_source_path($eventId, $photosByTile[$tile]);
    if ($photoPath === null) {
        continue;
    }

    $photoImage = image_from_path($photoPath);
    if (!$photoImage) {
        continue;
    }

    $srcW = imagesx($photoImage);
    $srcH = imagesy($photoImage);

    if ($srcW <= 0 || $srcH <= 0) {
        imagedestroy($photoImage);
        continue;
    }

    if ($mode === 'tilefull') {
        $scale = max($tileWidth / $srcW, $tileHeight / $srcH);
    } else {
        $scale = min($tileWidth / $srcW, $tileHeight / $srcH);
    }

    $drawW = (int) ceil($srcW * $scale);
    $drawH = (int) ceil($srcH * $scale);
    $drawX = $left + (int) floor(($tileWidth - $drawW) / 2);
    $drawY = $top + (int) floor(($tileHeight - $drawH) / 2);

    imagecopyresampled(
        $canvas,
        $photoImage,
        $drawX,
        $drawY,
        0,
        0,
        $drawW,
        $drawH,
        $srcW,
        $srcH
    );

    imagedestroy($photoImage);
}

$downloadBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($event['title'] ?? 'collage'));
$downloadName = $downloadBase . '_' . $mode . '.jpg';

save_output_image($canvas, $mode, $downloadName);
