<?php

include("../includes/db.php");

header("Content-Type: application/json");

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $eventID = (int) $_GET['id'];

    $sql = "SELECT filename, photonum
            FROM photos
            WHERE eventId = $eventID
              AND approved = 1
              AND photonum IS NOT NULL
              AND photonum > 0";

    $result = $mysqli->query($sql);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Sorry, the website is experiencing problems.'
        ]);
        $mysqli->close();
        exit;
    }

    $rows = [];

    while ($r = $result->fetch_assoc()) {
        $filename = $r['filename'];
        $photonum = (int)$r['photonum'];

        // Return the thumbnail JPG path.
        // Frontend will automatically try .webp first and fall back to .jpg.
        $rows[$photonum] = "/photos/$eventID/{$filename}_thumb.jpg";
    }

    $result->free();
    $mysqli->close();

    echo json_encode($rows);
    exit;
}

$mysqli->close();

echo json_encode([
    'success' => false,
    'message' => 'Invalid or missing event id.'
]);
exit;
?>
