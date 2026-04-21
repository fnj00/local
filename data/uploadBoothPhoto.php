<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include("../includes/db.php");

function jsonResponse($success, $message, $extra = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

function createImageFromFile($filePath, $mimeType) {
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

function fixOrientationIfNeeded($image, $filePath, $mimeType) {
    if (($mimeType !== 'image/jpeg' && $mimeType !== 'image/jpg') || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($filePath);
    if (!$exif || empty($exif['Orientation'])) {
        return $image;
    }

    switch ((int)$exif['Orientation']) {
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

function resizeImageResource($srcImage, $srcWidth, $srcHeight, $maxWidth, $maxHeight) {
    if ($srcWidth <= 0 || $srcHeight <= 0) {
        return false;
    }

    $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight);

    // Do not upscale
    if ($ratio > 1) {
        $ratio = 1;
    }

    $newWidth = max(1, (int)round($srcWidth * $ratio));
    $newHeight = max(1, (int)round($srcHeight * $ratio));

    $dstImage = imagecreatetruecolor($newWidth, $newHeight);
    if ($dstImage === false) {
        return false;
    }

    imagealphablending($dstImage, true);
    imagesavealpha($dstImage, true);

    if (!imagecopyresampled(
        $dstImage,
        $srcImage,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $srcWidth, $srcHeight
    )) {
        imagedestroy($dstImage);
        return false;
    }

    return $dstImage;
}

function saveDerivedImages($sourcePath, $mimeType, $jpgPath, $webpPath, $thumbJpgPath, $thumbWebpPath) {
    $sourceImage = createImageFromFile($sourcePath, $mimeType);
    if ($sourceImage === false) {
        return ['success' => false, 'message' => 'Failed to read uploaded image'];
    }

    $sourceImage = fixOrientationIfNeeded($sourceImage, $sourcePath, $mimeType);

    $srcWidth = imagesx($sourceImage);
    $srcHeight = imagesy($sourceImage);

    // Main display image max size
    $mainMaxWidth = 1400;
    $mainMaxHeight = 1400;

    // Thumbnail max size
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

if (!isset($_POST['eventId'])) {
    jsonResponse(false, 'Missing eventId');
}

if (!isset($_FILES['image'])) {
    jsonResponse(false, 'Missing uploaded image');
}

$eventidRaw = $_POST['eventId'];
if (!is_numeric($eventidRaw)) {
    jsonResponse(false, 'Invalid eventId');
}

$eventId = (int)$eventidRaw;
$uploadedFile = $_FILES['image'];

if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'Upload failed', ['code' => $uploadedFile['error']]);
}

if (!is_uploaded_file($uploadedFile['tmp_name'])) {
    jsonResponse(false, 'Invalid uploaded file');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
finfo_close($finfo);

$extensionMap = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/jpg'  => 'jpg',
    'image/webp' => 'webp'
];

if (!isset($extensionMap[$mimeType])) {
    jsonResponse(false, 'Unsupported image type', ['mime' => $mimeType]);
}

$ext = $extensionMap[$mimeType];

$sql = "SELECT * FROM events WHERE ID = $eventId";
$result = $mysqli->query($sql);

if (!$result || $result->num_rows === 0) {
    jsonResponse(false, 'Event not found');
}

$row = $result->fetch_assoc();
$result->free();

$numcollage = (int)$row['numcollage'];
$autoapprove = (int)$row['autoapprove'];

$collagemax = $numcollage + 1;

$sql1 = "SELECT photonum FROM photos 
         WHERE eventId = $eventId 
         AND photonum > 0 
         AND photonum < $collagemax 
         AND approved = '1'";

$result = $mysqli->query($sql1);

if ($result && $result->num_rows < $numcollage && $numcollage > 0) {
    $result->free();

    do {
        $photonum = rand(1, $numcollage);
        $check = $mysqli->query("SELECT * FROM photos 
                                 WHERE eventId = $eventId 
                                 AND photonum = '$photonum' 
                                 AND approved = '1'");
        $exists = $check && $check->num_rows > 0;
        if ($check) {
            $check->free();
        }
    } while ($exists);
} else {
    if ($result) {
        $result->free();
    }
    $photonum = 0;
}

$photoDir = "../photos/$eventId";
if (!file_exists($photoDir)) {
    if (!mkdir($photoDir, 0755, true)) {
        jsonResponse(false, 'Failed to create directory');
    }
}

$fileName = uniqid();

$jpgPath = "$photoDir/$fileName.jpg";
$webpPath = "$photoDir/$fileName.webp";
$thumbJpgPath = "$photoDir/$fileName" . "_thumb.jpg";
$thumbWebpPath = "$photoDir/$fileName" . "_thumb.webp";

// 👇 IMPORTANT: use temp upload file directly (no original save)
$sourcePath = $uploadedFile['tmp_name'];

$derived = saveDerivedImages(
    $sourcePath,
    $mimeType,
    $jpgPath,
    $webpPath,
    $thumbJpgPath,
    $thumbWebpPath
);

if (!$derived['success']) {
    jsonResponse(false, $derived['message']);
}

$FileNameEscaped = mysqli_real_escape_string($mysqli, $fileName);
//$approvedValue = $autoapprove ? 1 : 0;

$sql2 = "INSERT INTO photos (eventId, approved, filename, photonum)
         VALUES ('$eventId', '1', '$FileNameEscaped', '$photonum')";

if (!$mysqli->query($sql2)) {
    jsonResponse(false, 'DB insert failed', ['error' => $mysqli->error]);
}

$mysqli->close();

jsonResponse(true, 'Success!', [
    'filename' => $fileName,
    'jpg' => "$fileName.jpg",
    'webp' => file_exists($webpPath) ? "$fileName.webp" : null,
    'thumb_jpg' => "$fileName" . "_thumb.jpg",
    'thumb_webp' => file_exists($thumbWebpPath) ? "$fileName" . "_thumb.webp" : null,
    'approved' => $approvedValue,
    'photonum' => $photonum,
    'webp_supported' => $derived['webp_supported']
]);
?>
