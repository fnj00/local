<?php
include("../includes/db.php");

function fail_and_redirect(string $message, int $eventId = 0): void {
    $target = 'index.php';
    if ($eventId > 0) {
        $target .= '?edit=' . $eventId;
    }
    header('Location: ' . $target . '&upload_message=' . urlencode($message));
    exit;
}

$eventId = isset($_POST['eventId']) ? (int)$_POST['eventId'] : 0;

if ($eventId <= 0) {
    die('Missing event ID.');
}

$stmt = $mysqli->prepare("SELECT ID FROM events WHERE ID = ?");
$stmt->bind_param('i', $eventId);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();
$stmt->close();

if (!$event) {
    die('Event not found.');
}

if (!isset($_FILES['fileToUpload']) || $_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) {
    die('No file uploaded or upload failed.');
}

$check = getimagesize($_FILES['fileToUpload']['tmp_name']);
if ($check === false) {
    die('Uploaded file is not a valid image.');
}

$imgWidth = (int)$check[0];
$imgHeight = (int)$check[1];

$uploadDir = '../photos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$originalName = basename($_FILES['fileToUpload']['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true)) {
    die('Only JPG, JPEG, PNG, and GIF files are allowed.');
}

if ($_FILES['fileToUpload']['size'] > 30000000) {
    die('File is too large.');
}

$safeBaseName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
$storedFileName = $safeBaseName . '_' . time() . '.' . $extension;
$targetFile = $uploadDir . $storedFileName;

if (!move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $targetFile)) {
    die('Unable to move uploaded file.');
}

$stmt = $mysqli->prepare("
    UPDATE events
    SET collageimg = ?, imgHeight = ?, imgWidth = ?
    WHERE ID = ?
");
$stmt->bind_param('siii', $storedFileName, $imgHeight, $imgWidth, $eventId);

if (!$stmt->execute()) {
    $stmt->close();
    die('Unable to update event image metadata.');
}

$stmt->close();

header('Location: index.php?edit=' . $eventId);
exit;
