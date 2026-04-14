<?php

include("../includes/db.php");

if(isset($_GET['id']) && is_numeric($_GET['id'])) {
  $eventID = (int) $_GET['id'];
  $remoteAddr = $_SERVER['REMOTE_ADDR'];
//  if(is_numeric($_GET['fbid'])) {
//   $fbID = (int) $_GET['fbid'];
//  } else {
//    $fbID = "12345";
//  }
  if($_GET['show'] == "played") {
    $sql = "SELECT a.editable, a.ID as id, a.fbid, (SELECT case when COUNT(*) > 0 then 1 else 0 end AS Voted FROM votes WHERE songId = a.ID AND ip = '$remoteAddr') voted, (SELECT COUNT(*) FROM votes WHERE songId = a.ID) userVotes, a.songTitle, a.songArtist, a.requestedBy from songs a WHERE eventId = $eventID AND played = 1";
#    $sql = "SELECT editable, voted, id, userVotes, songTitle, songArtist, requestedBy, fbid, (SELECT a.editable, a.ID as id, a.fbid, (SELECT COUNT(*) FROM votes WHERE songId = a.ID AND fbid = a.fbid) voted, (SELECT COUNT(*) FROM votes WHERE songId = a.ID) userVotes, a.songTitle, a.songArtist, a.requestedBy, a.fbid from songs a WHERE eventId = $eventID AND played = 1)";
  } else if($_GET['show'] == "removed") {
    $sql = "SELECT a.editable, a.ID as id, a.fbid, (SELECT case when COUNT(*) > 0 then 1 else 0 end AS Voted FROM votes WHERE songId = a.ID AND ip = '$remoteAddr') voted, (SELECT COUNT(*) FROM votes WHERE songId = a.ID) userVotes, a.songTitle, a.songArtist, a.requestedBy from songs a WHERE eventId = $eventID AND played = 2";
#    $sql = "SELECT voted, editable, id, userVotes, songTitle, songArtist, requestedBy, fbid, (SELECT a.editable, a.ID as id, a.fbid, (SELECT COUNT(*) FROM votes WHERE songId = a.ID AND fbid = a.fbid) voted, (SELECT COUNT(*) FROM votes WHERE songId = a.ID) userVotes, a.songTitle, a.songArtist, a.requestedBy, a.fbid from songs a WHERE eventId = $eventID AND played = 2)";
  } else {
    $sql = "SELECT a.editable, a.ID as id, a.fbid, (SELECT case when COUNT(*) > 0 then 1 else 0 end AS Voted FROM votes WHERE songId = a.ID AND ip = '$remoteAddr') voted, (SELECT COUNT(*) FROM votes WHERE songId = a.ID) userVotes, a.songTitle, a.songArtist, a.requestedBy from songs a WHERE eventId = $eventID AND played = 0";
#    $sql = "SELECT voted, editable, id, userVotes, songTitle, songArtist, requestedBy, fbid, (SELECT a.editable, a.ID as id, a.fbid, (SELECT COUNT(*) FROM votes WHERE songId = a.ID AND fbid = a.fbid) voted, (SELECT COUNT(*) FROM votes WHERE songId = a.ID) userVotes, a.songTitle, a.songArtist, a.requestedBy, a.fbid from songs a WHERE eventId = $eventID AND played = 0)";
  }

  if (!$result = $mysqli->query($sql)) {
    // Oh no! The query failed. 
    echo "Sorry, the website is experiencing problems.";
    echo "Query: " . $sql . "\n";
    echo "Errno: " . $mysqli->errno . "\n";
    echo "Error: " . $mysqli->error . "\n";
    exit;
  } else {
    $rows = array();
    while($r = $result->fetch_assoc()) {
      $rows[] = array('editable' => (bool)$r['editable'], 'id' => $r['id'], 'fbid' => $r['fbid'], 'voted' => (bool)$r['voted'], 'userVotes' => $r['userVotes'], 'songTitle' => $r['songTitle'], 'songArtist' => $r['songArtist'], 'requestedBy' => $r['requestedBy']);
#      $rows[] = $r;
    }
    print json_encode($rows);
    exit;
  }
}

$mysqli->close();

?>
