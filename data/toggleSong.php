<?php

include("../includes/db.php");
header('Content-type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if(is_numeric($data['id']) && is_numeric($data['played'])) {
  $songId = (int) $data['id'];
  $played = (int) $data['played'];

  $sql = "UPDATE songs SET played = $played WHERE ID = $songId";

  if(!$result = $mysqli->query($sql)) {
    $response_array['message'] = "Error adding song";
    echo "Query: " . $sql . "\n";
    echo "Errno: " . $mysqli->errno . "\n";
    echo "Error: " . $mysqli->error . "\n";
  } else {
    $response_array['message'] = "Success!";
  }
  $response_array['success'] = $result;
  $mysqli->close();
} else {
  $response_array['success'] = "false";
  $response_array['message'] = "Post did not meet minimum requirements";
}

echo json_encode($response_array);  

?>
