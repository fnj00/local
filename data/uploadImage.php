<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

function json_response(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

function get_request_headers_compat(): array
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        return is_array($headers) ? $headers : [];
    }

    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) === 'HTTP_') {
            $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$key] = $value;
        }
    }

    return $headers;
}

function header_value(array $headers, string $name): ?string
{
    foreach ($headers as $key => $value) {
        if (strcasecmp($key, $name) === 0) {
            return is_string($value) ? trim($value) : null;
        }
    }

    return null;
}

function create_image_from_string_safe(string $binary)
{
    return @imagecreatefromstring($binary);
}

function create_image_from_file(string $path, string $mimeType)
{
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            return @imagecreatefromjpeg($path);
        case 'image/png':
            return @imagecreatefrompng($path);
        case 'image/gif':
            return @imagecreatefromgif($path);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
        default:
            return false;
    }
}

function fix_orientation_if_needed($image, string $sourcePath, string $mimeType)
{
    if (($mimeType !== 'image/jpeg' && $mimeType !== 'image/jpg') || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($sourcePath);
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

function resize_image_resource($srcImage, int $srcWidth, int $srcHeight, int $maxWidth, int $maxHeight)
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

function ensure_directory(string $path): bool
{
    if (is_dir($path)) {
        return true;
    }

    return mkdir($path, 0755, true) || is_dir($path);
}

function save_derived_images(
    string $tempPath,
    string $mimeType,
    string $jpgPath,
    string $webpPath,
    string $thumbJpgPath,
    string $thumbWebpPath
): array {
    $sourceImage = create_image_from_file($tempPath, $mimeType);
    if ($sourceImage === false) {
        return ['success' => false, 'message' => 'Failed to read uploaded image'];
    }

    $sourceImage = fix_orientation_if_needed($sourceImage, $tempPath, $mimeType);

    $srcWidth = imagesx($sourceImage);
    $srcHeight = imagesy($sourceImage);

    $mainImage = resize_image_resource($sourceImage, $srcWidth, $srcHeight, 1400, 1400);
    if ($mainImage === false) {
        imagedestroy($sourceImage);
        return ['success' => false, 'message' => 'Failed to resize main image'];
    }

    $thumbImage = resize_image_resource($sourceImage, $srcWidth, $srcHeight, 300, 300);
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method', [], 405);
}

$headers = get_request_headers_compat();

$fbidRaw = header_value($headers, 'Meta-Fbid');
$fullnameRaw = header_value($headers, 'Meta-Fullname');
$originalNameRaw = header_value($headers, 'Meta-Name');
$eventIdRaw = header_value($headers, 'Meta-Eventid');

if ($fbidRaw === null || $fullnameRaw === null || $originalNameRaw === null || $eventIdRaw === null) {
    json_response(false, 'Missing required upload headers', [], 400);
}

if (!is_numeric($fbidRaw) || !is_numeric($eventIdRaw)) {
    json_response(false, 'Invalid Meta-Fbid or Meta-Eventid', [], 400);
}

$fbid = (int) $fbidRaw;
$eventId = (int) $eventIdRaw;
$fullname = trim($fullnameRaw);
$originalName = trim($originalNameRaw);

if ($fbid <= 0 || $eventId <= 0) {
    json_response(false, 'Invalid upload metadata', [], 400);
}

if ($fullname === '') {
    $fullname = 'Guest Upload';
}

$binary = file_get_contents('php://input');
if ($binary === false || $binary === '') {
    json_response(false, 'No upload body received', [], 400);
}

if (strlen($binary) > 20 * 1024 * 1024) {
    json_response(false, 'Uploaded image is too large', [], 413);
}

$tmpPath = tempnam(sys_get_temp_dir(), 'imgup_');
if ($tmpPath === false) {
    json_response(false, 'Unable to create temp file', [], 500);
}

file_put_contents($tmpPath, $binary);

$imageInfo = @getimagesize($tmpPath);
if ($imageInfo === false || empty($imageInfo['mime'])) {
    @unlink($tmpPath);
    json_response(false, 'Uploaded body is not a valid image', [], 400);
}

$mimeType = strtolower((string) $imageInfo['mime']);
$allowedMimeTypes = ['image/gif', 'image/jpg', 'image/jpeg', 'image/png', 'image/webp'];

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    @unlink($tmpPath);
    json_response(false, 'Unsupported image type', ['mime' => $mimeType], 400);
}

$testImage = create_image_from_string_safe($binary);
if ($testImage === false) {
    @unlink($tmpPath);
    json_response(false, 'Unable to process uploaded image', [], 400);
}
imagedestroy($testImage);

$stmt = $mysqli->prepare("
    SELECT ID
    FROM events
    WHERE ID = ?
    LIMIT 1
");

if (!$stmt) {
    @unlink($tmpPath);
    json_response(false, 'Failed to prepare event lookup', [], 500);
}

$stmt->bind_param('i', $eventId);
$stmt->execute();
$result = $stmt->get_result();
$event = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$event) {
    @unlink($tmpPath);
    json_response(false, 'Event not found', [], 404);
}

$photoDir = __DIR__ . '/../photos/' . $eventId;
if (!ensure_directory($photoDir)) {
    @unlink($tmpPath);
    json_response(false, 'Failed to create photo directory', [], 500);
}

/*
 * Store the base filename only in the database.
 * All derived file types use this same base.
 */
$baseName = uniqid('upload_', true);

$jpgPath = $photoDir . '/' . $baseName . '.jpg';
$webpPath = $photoDir . '/' . $baseName . '.webp';
$thumbJpgPath = $photoDir . '/' . $baseName . '_thumb.jpg';
$thumbWebpPath = $photoDir . '/' . $baseName . '_thumb.webp';

$derived = save_derived_images(
    $tmpPath,
    $mimeType,
    $jpgPath,
    $webpPath,
    $thumbJpgPath,
    $thumbWebpPath
);

@unlink($tmpPath);

if (!$derived['success']) {
    json_response(false, $derived['message'], [], 500);
}

$approved = 0;

$stmt = $mysqli->prepare("
    INSERT INTO photos (eventId, approved, filename, uploadedBy, uploadedfbId)
    VALUES (?, ?, ?, ?, ?)
");

if (!$stmt) {
    @unlink($jpgPath);
    @unlink($webpPath);
    @unlink($thumbJpgPath);
    @unlink($thumbWebpPath);
    json_response(false, 'Failed to prepare photo insert', [], 500);
}

$stmt->bind_param('iissi', $eventId, $approved, $baseName, $fullname, $fbid);
$ok = $stmt->execute();
$newPhotoId = $mysqli->insert_id;
$stmt->close();

if (!$ok) {
    @unlink($jpgPath);
    @unlink($webpPath);
    @unlink($thumbJpgPath);
    @unlink($thumbWebpPath);
    json_response(false, 'Failed to save photo record', [], 500);
}

json_response(true, 'Photo uploaded successfully.', [
    'photoId' => (int) $newPhotoId,
    'eventId' => $eventId,
    'filename' => $baseName,
    'approved' => false,
    'uploadedBy' => $fullname,
    'uploadedfbId' => $fbid,
    'imageUrl' => '/photos/' . $eventId . '/' . rawurlencode($baseName) . '.jpg',
    'thumbUrl' => '/photos/' . $eventId . '/' . rawurlencode($baseName) . '_thumb.jpg',
    'webp' => (function_exists('imagewebp') && file_exists($webpPath)) ? $baseName . '.webp' : null,
    'thumb_webp' => (function_exists('imagewebp') && file_exists($thumbWebpPath)) ? $baseName . '_thumb.webp' : null,
    'originalMetaName' => $originalName
]);
