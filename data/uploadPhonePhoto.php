<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

function jsonResponse(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

function createImageFromFile(string $filePath, string $mimeType)
{
    switch ($mimeType) {
        case 'image/png':
            return @imagecreatefrompng($filePath);
        case 'image/jpeg':
        case 'image/jpg':
            return @imagecreatefromjpeg($filePath);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false;
        default:
            return false;
    }
}

function fixOrientationIfNeeded($image, string $filePath, string $mimeType)
{
    if (($mimeType !== 'image/jpeg' && $mimeType !== 'image/jpg') || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($filePath);
    if (!$exif || empty($exif['Orientation'])) {
        return $image;
    }

    switch ((int) $exif['Orientation']) {
        case 3:
            $image = imagerotate($image, 180, 0);
            break;
        case 6:
            $image = imagerotate($image, -90, 0);
            break;
        case 8:
            $image = imagerotate($image, 90, 0);
            break;
    }

    return $image;
}

function resizeImageResource($srcImage, int $srcWidth, int $srcHeight, int $maxWidth, int $maxHeight)
{
    if ($srcWidth <= 0 || $srcHeight <= 0) {
        return false;
    }

    $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight);

    if ($ratio > 1) {
        $ratio = 1;
    }

    $newWidth = max(1, (int) round($srcWidth * $ratio));
    $newHeight = max(1, (int) round($srcHeight * $ratio));

    $dstImage = imagecreatetruecolor($newWidth, $newHeight);
    if ($dstImage === false) {
        return false;
    }

    $white = imagecolorallocate($dstImage, 255, 255, 255);
    imagefill($dstImage, 0, 0, $white);

    if (!imagecopyresampled(
        $dstImage,
        $srcImage,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        $srcWidth,
        $srcHeight
    )) {
        imagedestroy($dstImage);
        return false;
    }

    return $dstImage;
}

function saveDerivedImages(
    string $sourcePath,
    string $mimeType,
    string $jpgPath,
    string $webpPath,
    string $thumbJpgPath,
    string $thumbWebpPath
): array {
    $sourceImage = createImageFromFile($sourcePath, $mimeType);
    if ($sourceImage === false) {
        return ['success' => false, 'message' => 'Failed to read uploaded image'];
    }

    $sourceImage = fixOrientationIfNeeded($sourceImage, $sourcePath, $mimeType);

    $srcWidth = imagesx($sourceImage);
    $srcHeight = imagesy($sourceImage);

    $mainMaxWidth = 1400;
    $mainMaxHeight = 1400;

    $thumbMaxWidth = 300;
    $thumbMaxHeight = 300;

    $mainImage = resizeImageResource($sourceImage, $srcWidth, $srcHeight, $mainMaxWidth, $mainMaxHeight);
    if ($mainImage === false) {
        imagedestroy($sourceImage);
        return ['success' => false, 'message' => 'Failed to resize main image'];
    }

    $thumbImage = resizeImageResource($sourceImage, $srcWidth, $srcHeight, $thumbMaxWidth, $thumbMaxHeight);
    if ($thumbImage === false) {
        imagedestroy($sourceImage);
        imagedestroy($mainImage);
        return ['success' => false, 'message' => 'Failed to generate thumbnail'];
    }

    $jpgSaved = imagejpeg($mainImage, $jpgPath, 90);
    $thumbJpgSaved = imagejpeg($thumbImage, $thumbJpgPath, 78);

    $webpSaved = true;
    $thumbWebpSaved = true;

    if (function_exists('imagewebp')) {
        $webpSaved = imagewebp($mainImage, $webpPath, 80);
        $thumbWebpSaved = imagewebp($thumbImage, $thumbWebpPath, 75);
    }

    imagedestroy($sourceImage);
    imagedestroy($mainImage);
    imagedestroy($thumbImage);

    if (!$jpgSaved || !$thumbJpgSaved) {
        return ['success' => false, 'message' => 'Failed to save JPG outputs'];
    }

    return [
        'success' => true,
        'webp_supported' => function_exists('imagewebp'),
        'webp_saved' => $webpSaved,
        'thumb_webp_saved' => $thumbWebpSaved
    ];
}

function findRandomOpenTile(mysqli $mysqli, int $eventId, int $numCollage): int
{
    if ($numCollage <= 0) {
        return 0;
    }

    $used = [];
    $stmt = $mysqli->prepare("
        SELECT photonum
        FROM photos
        WHERE eventId = ? AND approved = 1 AND photonum > 0 AND photonum <= ?
    ");

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('ii', $eventId, $numCollage);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($result && ($row = $result->fetch_assoc())) {
        $used[] = (int) $row['photonum'];
    }

    $stmt->close();

    $available = [];
    for ($i = 1; $i <= $numCollage; $i++) {
        if (!in_array($i, $used, true)) {
            $available[] = $i;
        }
    }

    if (!$available) {
        return 0;
    }

    return (int) $available[array_rand($available)];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method', [], 405);
}

/*
 * Support both classic form posts and JSON request bodies.
 */
$input = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $jsonBody = json_decode($rawBody, true);

    if (is_array($jsonBody)) {
        $input = $jsonBody;
    }
}

if (!isset($input['eventId'])) {
    jsonResponse(false, 'Missing eventId', [], 400);
}

if (!isset($input['image'])) {
    jsonResponse(false, 'Missing image', [], 400);
}

$assignOpenTiles = !empty($input['assignOpenTiles']) ? 1 : 0;
$eventIdRaw = $input['eventId'];
$imageRaw = $input['image'];
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

if (!is_numeric($eventIdRaw)) {
    jsonResponse(false, 'Invalid eventId', [], 400);
}

$eventId = (int) $eventIdRaw;
if ($eventId <= 0) {
    jsonResponse(false, 'Invalid eventId', [], 400);
}

if (!is_string($imageRaw) || $imageRaw === '') {
    jsonResponse(false, 'Invalid image payload', [], 400);
}

if (!preg_match('#^data:image/([a-zA-Z0-9.+-]+);base64,#', $imageRaw, $matches)) {
    jsonResponse(false, 'Invalid image payload', [], 400);
}

$mimeSubtype = strtolower($matches[1]);
$mimeMap = [
    'png' => 'image/png',
    'jpeg' => 'image/jpeg',
    'jpg' => 'image/jpg',
    'webp' => 'image/webp'
];

if (!isset($mimeMap[$mimeSubtype])) {
    jsonResponse(false, 'Unsupported image type', ['mime' => $mimeSubtype], 400);
}

$mimeType = $mimeMap[$mimeSubtype];
$base64Data = preg_replace('#^data:image/[a-zA-Z0-9.+-]+;base64,#', '', $imageRaw);
$binary = base64_decode(str_replace(' ', '+', $base64Data), true);

if ($binary === false || $binary === '') {
    jsonResponse(false, 'Failed to decode image data', [], 400);
}

if (strlen($binary) > 20 * 1024 * 1024) {
    jsonResponse(false, 'Image is too large', [], 413);
}

$stmt = $mysqli->prepare("SELECT ID, numcollage, autoapprove FROM events WHERE ID = ? LIMIT 1");
if (!$stmt) {
    jsonResponse(false, 'Failed to prepare event lookup', [], 500);
}

$stmt->bind_param('i', $eventId);
$stmt->execute();
$result = $stmt->get_result();
$event = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$event) {
    jsonResponse(false, 'Event not found', [], 404);
}

$numCollage = (int) ($event['numcollage'] ?? 0);
$autoapprove = (int) ($event['autoapprove'] ?? 0);

$photoDir = __DIR__ . '/../photos/' . $eventId;
if (!is_dir($photoDir) && !mkdir($photoDir, 0755, true) && !is_dir($photoDir)) {
    jsonResponse(false, 'Failed to create photo directory', [], 500);
}

$tempPath = tempnam(sys_get_temp_dir(), 'phoneup_');
if ($tempPath === false) {
    jsonResponse(false, 'Failed to create temp file', [], 500);
}

if (file_put_contents($tempPath, $binary) === false) {
    @unlink($tempPath);
    jsonResponse(false, 'Failed to write temp image', [], 500);
}

$fileName = uniqid('phone_', true);

$jpgPath = $photoDir . '/' . $fileName . '.jpg';
$webpPath = $photoDir . '/' . $fileName . '.webp';
$thumbJpgPath = $photoDir . '/' . $fileName . '_thumb.jpg';
$thumbWebpPath = $photoDir . '/' . $fileName . '_thumb.webp';

$derived = saveDerivedImages(
    $tempPath,
    $mimeType,
    $jpgPath,
    $webpPath,
    $thumbJpgPath,
    $thumbWebpPath
);

@unlink($tempPath);

if (!$derived['success']) {
    jsonResponse(false, $derived['message'], [], 500);
}

$photonum = 0;
if ($autoapprove === 1 && $assignOpenTiles === 1) {
    $photonum = findRandomOpenTile($mysqli, $eventId, $numCollage);
}

$stmt = $mysqli->prepare("
    INSERT INTO photos (eventId, approved, filename, ip, photonum)
    VALUES (?, ?, ?, ?, ?)
");

if (!$stmt) {
    @unlink($jpgPath);
    @unlink($webpPath);
    @unlink($thumbJpgPath);
    @unlink($thumbWebpPath);
    jsonResponse(false, 'Failed to prepare photo insert', [], 500);
}

$stmt->bind_param('iissi', $eventId, $autoapprove, $fileName, $remoteAddr, $photonum);
$ok = $stmt->execute();
$newPhotoId = $mysqli->insert_id;
$stmt->close();

if (!$ok) {
    @unlink($jpgPath);
    @unlink($webpPath);
    @unlink($thumbJpgPath);
    @unlink($thumbWebpPath);
    jsonResponse(false, 'Failed to save photo record', [], 500);
}

$message = 'Photo uploaded successfully and is waiting for approval.';
if ($autoapprove === 1 && $photonum > 0) {
    $message = 'Photo uploaded and added to the collage.';
} elseif ($autoapprove === 1) {
    $message = 'Photo uploaded successfully. The collage is currently full, so your photo is saved but not placed yet.';
}

jsonResponse(true, $message, [
    'photoId' => (int) $newPhotoId,
    'eventId' => $eventId,
    'filename' => $fileName,
    'approved' => $autoapprove === 1,
    'photonum' => $photonum,
    'jpg' => $fileName . '.jpg',
    'webp' => (function_exists('imagewebp') && file_exists($webpPath)) ? $fileName . '.webp' : null,
    'thumb_jpg' => $fileName . '_thumb.jpg',
    'thumb_webp' => (function_exists('imagewebp') && file_exists($thumbWebpPath)) ? $fileName . '_thumb.webp' : null,
    'imageUrl' => '/photos/' . $eventId . '/' . rawurlencode($fileName) . '.jpg',
    'thumbUrl' => '/photos/' . $eventId . '/' . rawurlencode($fileName) . '_thumb.jpg',
    'webp_supported' => $derived['webp_supported']
]);
