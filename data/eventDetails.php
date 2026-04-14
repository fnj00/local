<?php

include("../includes/db.php");

if(isset($_GET['id']) && is_numeric($_GET['id'])) {
  $eventID = (int) $_GET['id'];

  $sql = "SELECT * from events WHERE ID = $eventID";
  if (!$result = $mysqli->query($sql)) {
    // Oh no! The query failed. 
    echo "Sorry, the website is experiencing problems.";
    exit;
  }
  if ($result->num_rows === 0) {
    echo "We could not find a match for Event ID $eventID, sorry about that. Please try again.";
    exit;
  } else {
    $rows = array();
    while($r = $result->fetch_assoc()) {
      $rows = array('id' => $r['ID'], 'title' => $r['title'], 'date' => $r['date'], 'collageImg' => $r['collageimg'], 'collageX' => $r['collagex'], 'collageY' => $r['collagey'], 'numCollage' => $r['numcollage'], 'imgWidth' => $r['imgWidth'], 'imgHeight' => $r['imgHeight']);
    }
    print json_encode($rows);
    exit;
  }
}
$mysqli->close();

?>
