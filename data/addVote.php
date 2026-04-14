<?php

include("../includes/db.php");
header('Content-type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if($data['voted'] == TRUE && is_numeric($data['id'])) {
  $songID = (int) $data['id'];
  $remoteAddr = $_SERVER['REMOTE_ADDR'];

  $sql = "SELECT * FROM votes WHERE songId = $songID AND ip = '$remoteAddr'";

  if (!$result = $mysqli->query($sql)) {
    $response_array['message'] = "Error running vote search query";
    exit;
  }

  if ($result->num_rows === 0) {

    $sql2 = "INSERT into votes (songId, ip) VALUES ('$songID', '$remoteAddr')";

    if(!$result2 = $mysqli->query($sql2)) {
      $response_array['message'] = "Error adding song";
      exit;
    } else {
      $response_array['message'] = "Success!";
    }
    $response_array['success'] = $result2;
    $mysqli->close();
  } else {
    $response_array['success'] = $result2;
    $response_array['errors'] = "You have already voted for this.";
  }

}

echo json_encode($response_array);  

?>
