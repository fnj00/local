<?php
session_start();

if (empty($_SESSION['collage_admin_logged_in'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(array('error' => 'Unauthorized'));
    exit;
}
header('Content-Type: application/json');

include("../../includes/db.php");

$action = isset($_POST['action']) ? $_POST['action'] : '';
$eventId = isset($_POST['eventId']) && is_numeric($_POST['eventId']) ? (int)$_POST['eventId'] : 0;

if ($eventId <= 0) {
    echo json_encode(array('success' => false, 'message' => 'Missing event id'));
    exit;
}

function get_empty_tiles($mysqli, $eventId) {
    $eventId = (int)$eventId;

    $eventSql = "SELECT collagex, collagey FROM events WHERE ID = $eventId";
    $eventResult = $mysqli->query($eventSql);

    if (!$eventResult || $eventResult->num_rows === 0) {
        return array();
    }

    $eventRow = $eventResult->fetch_assoc();
    $collageX = isset($eventRow['collagex']) ? (int)$eventRow['collagex'] : 0;
    $collageY = isset($eventRow['collagey']) ? (int)$eventRow['collagey'] : 0;

    if ($collageX <= 0 || $collageY <= 0) {
        return array();
    }

    $totalTiles = $collageX * $collageY;

    $usedTiles = array();
    $visibleSql = "SELECT photonum FROM photos WHERE eventId = $eventId AND approved = 1 AND photonum IS NOT NULL AND photonum > 0";
    $visibleResult = $mysqli->query($visibleSql);

    if ($visibleResult) {
        while ($row = $visibleResult->fetch_assoc()) {
            $usedTiles[] = (int)$row['photonum'];
        }
    }

    $emptyTiles = array();
    for ($i = 1; $i <= $totalTiles; $i++) {
        if (!in_array($i, $usedTiles, true)) {
            $emptyTiles[] = $i;
        }
    }

    return $emptyTiles;
}

function load_image_resource($path, $ext) {
    $ext = strtolower($ext);

    if ($ext === 'jpg' || $ext === 'jpeg') {
        return @imagecreatefromjpeg($path);
    }

    if ($ext === 'png') {
        return @imagecreatefrompng($path);
    }

    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($path);
    }

    return false;
}

function generate_photo_thumbs($mysqli, $eventId, $photoId) {
    $eventId = (int)$eventId;
    $photoId = (int)$photoId;

    if ($eventId <= 0 || $photoId <= 0) {
        return array('success' => false, 'message' => 'Missing eventId or photoId');
    }

    $sql = "SELECT ID, filename FROM photos WHERE ID = $photoId AND eventId = $eventId LIMIT 1";
    $result = $mysqli->query($sql);

    if (!$result || $result->num_rows === 0) {
        return array('success' => false, 'message' => 'Photo not found');
    }

    $row = $result->fetch_assoc();
    $baseName = $row['filename'];

    $photoDir = realpath(__DIR__ . "/../../photos/$eventId");
    if ($photoDir === false) {
        return array('success' => false, 'message' => 'Photo directory not found');
    }

    $sourcePath = '';
    $sourceExt = '';

    $candidates = array('jpg', 'jpeg', 'png', 'webp');
    foreach ($candidates as $ext) {
        $candidate = $photoDir . DIRECTORY_SEPARATOR . $baseName . '.' . $ext;
        if (file_exists($candidate)) {
            $sourcePath = $candidate;
            $sourceExt = $ext;
            break;
        }
    }

    if ($sourcePath === '') {
        return array('success' => false, 'message' => 'Original image file not found');
    }

    $thumbJpgPath = $photoDir . DIRECTORY_SEPARATOR . $baseName . '_thumb.jpg';
    $thumbWebpPath = $photoDir . DIRECTORY_SEPARATOR . $baseName . '_thumb.webp';

    $src = load_image_resource($sourcePath, $sourceExt);
    if (!$src) {
        return array('success' => false, 'message' => 'Unable to load source image');
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);

    if ($srcW <= 0 || $srcH <= 0) {
        imagedestroy($src);
        return array('success' => false, 'message' => 'Invalid source image dimensions');
    }

    /*
        collage.js expects:
        - basename_thumb.jpg
        - basename_thumb.webp
        and uses object-fit: cover, so a square thumb works well.
    */
    $thumbSize = 600;
    $scale = max($thumbSize / $srcW, $thumbSize / $srcH);
    $resizedW = (int)ceil($srcW * $scale);
    $resizedH = (int)ceil($srcH * $scale);

    $tmp = imagecreatetruecolor($resizedW, $resizedH);
    $white = imagecolorallocate($tmp, 255, 255, 255);
    imagefill($tmp, 0, 0, $white);
    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $resizedW, $resizedH, $srcW, $srcH);

    $thumb = imagecreatetruecolor($thumbSize, $thumbSize);
    imagefill($thumb, 0, 0, $white);

    $cropX = (int)floor(($resizedW - $thumbSize) / 2);
    $cropY = (int)floor(($resizedH - $thumbSize) / 2);

    imagecopy($thumb, $tmp, 0, 0, $cropX, $cropY, $thumbSize, $thumbSize);

    $savedJpg = @imagejpeg($thumb, $thumbJpgPath, 90);
    $savedWebp = false;
    if (function_exists('imagewebp')) {
        $savedWebp = @imagewebp($thumb, $thumbWebpPath, 90);
    }

    if ($savedJpg && file_exists($thumbJpgPath)) {
        @chmod($thumbJpgPath, 0644);
    }
    if ($savedWebp && file_exists($thumbWebpPath)) {
        @chmod($thumbWebpPath, 0644);
    }

    imagedestroy($src);
    imagedestroy($tmp);
    imagedestroy($thumb);

    return array(
        'success' => true,
        'thumbJpg' => "/photos/$eventId/" . $baseName . "_thumb.jpg",
        'thumbWebp' => $savedWebp ? "/photos/$eventId/" . $baseName . "_thumb.webp" : null
    );
}

if ($action === 'remove') {
    $tileId = isset($_POST['tileId']) && is_numeric($_POST['tileId']) ? (int)$_POST['tileId'] : 0;

    if ($tileId <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Missing tile id'));
        exit;
    }

    $sql = "UPDATE photos SET photonum = NULL WHERE eventId = $eventId AND photonum = $tileId AND approved = 1";
    if (!$mysqli->query($sql)) {
        echo json_encode(array('success' => false, 'message' => 'Unable to remove photo from mosaic'));
        exit;
    }

    echo json_encode(array('success' => true));
    exit;
}

if ($action === 'assign') {
    $photoId = isset($_POST['photoId']) && is_numeric($_POST['photoId']) ? (int)$_POST['photoId'] : 0;
    $tileId = isset($_POST['tileId']) && is_numeric($_POST['tileId']) ? (int)$_POST['tileId'] : 0;

    if ($photoId <= 0 || $tileId <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Missing photo id or tile id'));
        exit;
    }

    $checkSql = "SELECT COUNT(*) AS total FROM photos WHERE eventId = $eventId AND approved = 1 AND photonum = $tileId";
    if (!$checkResult = $mysqli->query($checkSql)) {
        echo json_encode(array('success' => false, 'message' => 'Unable to validate tile occupancy'));
        exit;
    }

    $checkRow = $checkResult->fetch_assoc();
    if ((int)$checkRow['total'] > 0) {
        echo json_encode(array('success' => false, 'message' => 'That tile is already occupied'));
        exit;
    }

    $assignSql = "UPDATE photos SET approved = 1, photonum = $tileId WHERE ID = $photoId AND eventId = $eventId";
    if (!$mysqli->query($assignSql)) {
        echo json_encode(array('success' => false, 'message' => 'Unable to assign photo to tile'));
        exit;
    }

    $thumbResult = generate_photo_thumbs($mysqli, $eventId, $photoId);

    echo json_encode(array(
        'success' => true,
        'thumbs' => $thumbResult
    ));
    exit;
}

if ($action === 'approve_random') {
    $photoId = isset($_POST['photoId']) && is_numeric($_POST['photoId']) ? (int)$_POST['photoId'] : 0;

    if ($photoId <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Missing photo id'));
        exit;
    }

    $emptyTiles = get_empty_tiles($mysqli, $eventId);

    if (count($emptyTiles) === 0) {
        echo json_encode(array('success' => false, 'message' => 'No empty tiles are available'));
        exit;
    }

    $randomIndex = array_rand($emptyTiles);
    $tileId = (int)$emptyTiles[$randomIndex];

    $assignSql = "UPDATE photos SET approved = 1, photonum = $tileId WHERE ID = $photoId AND eventId = $eventId";
    if (!$mysqli->query($assignSql)) {
        echo json_encode(array('success' => false, 'message' => 'Unable to approve photo and assign random tile'));
        exit;
    }

    $thumbResult = generate_photo_thumbs($mysqli, $eventId, $photoId);

    echo json_encode(array(
        'success' => true,
        'tileId' => $tileId,
        'thumbs' => $thumbResult
    ));
    exit;
}

if ($action === 'approve_only') {
    $photoId = isset($_POST['photoId']) && is_numeric($_POST['photoId']) ? (int)$_POST['photoId'] : 0;

    if ($photoId <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Missing photo id'));
        exit;
    }

    $sql = "UPDATE photos SET approved = 1 WHERE ID = $photoId AND eventId = $eventId";
    if (!$mysqli->query($sql)) {
        echo json_encode(array('success' => false, 'message' => 'Unable to approve photo'));
        exit;
    }

    echo json_encode(array('success' => true));
    exit;
}

if ($action === 'generate_thumbs') {
    $photoId = isset($_POST['photoId']) && is_numeric($_POST['photoId']) ? (int)$_POST['photoId'] : 0;

    if ($photoId <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Missing photo id'));
        exit;
    }

    $thumbResult = generate_photo_thumbs($mysqli, $eventId, $photoId);
    echo json_encode($thumbResult);
    exit;
}

echo json_encode(array('success' => false, 'message' => 'Unknown action'));
$mysqli->close();
?>
