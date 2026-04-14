<?php

header("Access-Control-Allow-Origin: *");

include("../includes/db.php");

date_default_timezone_set("America/Detroit");

$date = date("Y-m-d", strtotime('-4 hours'));

$id = $_GET['eventId'];

if ( $id > '0'){
  $sql = "SELECT * from events WHERE `id` = '$id'";
} else {
  $sql = "SELECT * from events WHERE `date` >= '$date' ORDER BY `date` ASC limit 1";
}

if (!$result = $mysqli->query($sql)) {
  // Oh no! The query failed.
  echo "Sorry, the website is experiencing problems.";
  exit;
}
if ($result->num_rows === 0) {
  echo "We could not find a Event on or after $date, sorry about that. Please try again.";
  exit;
} else {
  $rows = array();
    while($r = $result->fetch_assoc()) {
      $formatdate = date('F d, Y', strtotime($r['date']));
      $rows = array('id' => $r['ID'], 'title' => $r['title'], 'date' => $r['date'], 'collageImg' => $r['collageimg'], 'collageX' => $r['collagex'], 'collageY' => $r['collagey'], 'numCollage' => $r['numcollage'], 'imgWidth' => $r['imgWidth'], 'imgHeight' => $r['imgHeight']);
    }
    print json_encode($rows);
    exit;
}
$mysqli->close();

?>
