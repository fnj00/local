<?php

include("../includes/db.php");

if(isset($_GET['id']) && is_numeric($_GET['id'])) {
  $eventID = (int) $_GET['id'];
  $sql = "SELECT * from photos WHERE eventId = $eventID AND approved = 1 AND photonum IS NOT NULL AND photonum > '0'";

  if (!$result = $mysqli->query($sql)) {
    // Oh no! The query failed.
    echo "Sorry, the website is experiencing problems.";
    exit;
  } else {
    $rows = array();
    while ($r = $result->fetch_assoc() ) {
      $filename = $r['filename'];
      $rows[$r['photonum']] = "/photos/$eventID/$filename.jpg";
    }
    print json_encode($rows);
    exit;
  }
}

$mysqli->close();

?>
