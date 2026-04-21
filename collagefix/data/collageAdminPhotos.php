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

$eventId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($eventId <= 0) {
    echo json_encode(array('error' => 'Missing event id'));
    exit;
}

$eventSql = "SELECT * FROM events WHERE ID = $eventId";
if (!$eventResult = $mysqli->query($eventSql)) {
    echo json_encode(array('error' => 'Unable to load event'));
    exit;
}

if ($eventResult->num_rows === 0) {
    echo json_encode(array('error' => 'Event not found'));
    exit;
}

$eventRow = $eventResult->fetch_assoc();

$event = array(
    'id' => $eventRow['ID'],
    'title' => $eventRow['title'],
    'date' => $eventRow['date'],
    'collageImg' => '../photos/' . $eventRow['collageimg'],
    'collageX' => $eventRow['collagex'],
    'collageY' => $eventRow['collagey'],
    'imgWidth' => $eventRow['imgWidth'],
    'imgHeight' => $eventRow['imgHeight']
);

/*
    Visible photos:
    - approved = 1
    - photonum > 0
*/
$visibleSql = "SELECT * FROM photos WHERE eventId = $eventId AND approved = 1 AND photonum IS NOT NULL AND photonum > 0 ORDER BY photonum ASC";
if (!$visibleResult = $mysqli->query($visibleSql)) {
    echo json_encode(array('error' => 'Unable to load visible photos'));
    exit;
}

$visible = array();
$usedTiles = array();

while ($row = $visibleResult->fetch_assoc()) {
    $photoId = $row['ID'];
    $filename = $row['filename'];
    $photoUrl = "/photos/$eventId/$filename.jpg";
    $photonum = isset($row['photonum']) ? (int)$row['photonum'] : 0;
    $approved = isset($row['approved']) ? (int)$row['approved'] : 0;

    $visible[] = array(
        'photoId' => $photoId,
        'tileId' => $photonum,
        'fileName' => $filename . '.jpg',
        'photoUrl' => $photoUrl,
        'approved' => $approved
    );

    $usedTiles[] = $photonum;
}

/*
    Hidden photos:
    - approved = 0
    OR
    - photonum IS NULL
    OR
    - photonum <= 0

    This intentionally includes unapproved photos.
*/
$hiddenSql = "SELECT * FROM photos WHERE eventId = $eventId AND (approved = 0 OR photonum IS NULL OR photonum <= 0) ORDER BY ID DESC";
if (!$hiddenResult = $mysqli->query($hiddenSql)) {
    echo json_encode(array('error' => 'Unable to load hidden photos'));
    exit;
}

$hidden = array();

while ($row = $hiddenResult->fetch_assoc()) {
    $photoId = $row['ID'];
    $filename = $row['filename'];
    $photoUrl = "/photos/$eventId/$filename.jpg";
    $photonum = isset($row['photonum']) ? (int)$row['photonum'] : 0;
    $approved = isset($row['approved']) ? (int)$row['approved'] : 0;

    $hidden[] = array(
        'photoId' => $photoId,
        'fileName' => $filename . '.jpg',
        'photoUrl' => $photoUrl,
        'approved' => $approved,
        'photonum' => $photonum
    );
}

$totalTiles = ((int)$event['collageX']) * ((int)$event['collageY']);
$emptyTiles = array();

for ($i = 1; $i <= $totalTiles; $i++) {
    if (!in_array($i, $usedTiles, true)) {
        $emptyTiles[] = $i;
    }
}

echo json_encode(array(
    'event' => $event,
    'visible' => $visible,
    'hidden' => $hidden,
    'emptyTiles' => $emptyTiles
));

$mysqli->close();
?>
