<?php

include("../includes/db.php");

if(isset($_GET['id']) && is_numeric($_GET['id'])) {
  $eventID = (int) $_GET['id'];
  if(isset($_GET['fbid'])) {
    $fbID = (int) $_GET['fbid'];
    if($_GET['show'] == "approved") {
      $sql = "SELECT * from photos WHERE eventId = $eventID AND approved = 1 AND uploadedfbId = $fbID";
    } else {
      $sql = "SELECT * from photos WHERE eventId = $eventID AND approved = 0 AND uploadedfbId = $fbID";
    }
  } else if(isset($_GET['phone'])) {
    $remoteAddr = $_SERVER['REMOTE_ADDR'];
    if($_GET['show'] == "approved") {
      $sql = "SELECT * from photos WHERE eventId = $eventID AND approved = 1 AND ip = '$remoteAddr'";
    } else {
      $sql = "SELECT * from photos WHERE eventId = $eventID AND approved = 0 AND ip = '$remoteAddr'";
    }
  } else {
    if($_GET['show'] == "approved") {
      $sql = "SELECT * from photos WHERE eventId = $eventID AND approved = 1";
    } else if($_GET['show'] == "denied") {
      $sql = "SELECT * from photos WHERE eventId = $eventID AND approved = 2";
    } else {
      $sql = "SELECT * from photos WHERE eventId = $eventID AND approved = 0";
    }
  }
  if (!$result = $mysqli->query($sql)) {
    // Oh no! The query failed. 
    echo "Sorry, the website is experiencing problems.";
    exit;
  } else {
    $rows = array();
    while($r = $result->fetch_assoc()) {
      $rows[] = array('id' => $r['ID'], 'eventId' => $r['eventId'], 'approved' => $r['approved'], 'name' => $r['filename'], 'photonum' => $r['photonum'], 'ip' => $r['ip']);
    }
    print json_encode($rows);
    exit;
  }
}

$mysqli->close();

?>
