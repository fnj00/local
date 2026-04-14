<?php

include("../../includes/db.php");

$sql = "SELECT * from events";
if (!$result = $mysqli->query($sql)) {
  // Oh no! The query failed. 
  echo "Sorry, the website is experiencing problems.";
  exit;
}
if ($result->num_rows === 0) {
  echo "We could not find any events, sorry about that. Please try again.";
  exit;
} else {
  $rows = array();
  while($r = $result->fetch_assoc()) {
    $rows[] = array('id' => $r['ID'], 'title' => $r['title'], 'date' => $r['date']);
  }
  print json_encode($rows);
  exit;
}
$mysqli->close();
?>
