<?php

include("../includes/db.php");
header('Content-type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if(isset($data['song']) && is_numeric($data['eventId'])) {
  $eventID = (int) $data['eventId'];
  $remoteAddr = $_SERVER['REMOTE_ADDR'];
  $song = mysqli_real_escape_string($mysqli, $data['song']);
  $artist = mysqli_real_escape_string($mysqli, $data['artist']);

  $sql = "SELECT * FROM songs WHERE songTitle = '$song' AND songArtist = '$artist' AND eventId = '$eventID'";

  if (!$result = $mysqli->query($sql)) {
    $response_array['success'] = $result;
    $response_array['message'] = "Error running db song search query";
    echo json_encode($response_array);
    exit;
  }

  if ($result->num_rows === 0) {

    $sql1 = "INSERT into songs (eventId, editable, played, songTitle, songArtist, ip) VALUES ('$eventID', '0', '0', '$song', '$artist', '$remoteAddr')";

    if(!$result1 = $mysqli->query($sql1)) {
      $response_array['message'] = "Error adding song request";
    } else {
      $response_array['message'] = "Success!";
    }
    $response_array['success'] = $result1;
    $mysqli->close();
  } else {
    // Check if song is played or not
    $r = $result->fetch_assoc();
    $response_array['success'] = false;
    if($r['played'] == "1") {
      $response_array['message'] = "This song has already been played, please request another song.";
    } else if($r['played'] == "2") {
      $response_array['message'] = "This song has been blocked/removed by the DJ.";
    } else {
      $response_array['message'] = "This song has already been reqeusted, plese vote for the song instead.";
    }
  }

} else {
  $response_array['success'] = "false";
  $response_array['message'] = "Post did not meet minimum requirements";
}

echo json_encode($response_array);  

?>
