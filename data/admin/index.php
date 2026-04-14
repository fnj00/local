<?php

include("../../includes/db.php");
header('Content-type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if($data['action'] == "create_event") {
  $title = mysqli_real_escape_string($mysqli, $data['title']);
  $date = mysqli_real_escape_string($mysqli, date("Y-m-d", strtotime($data['date'])));

  $sql = "INSERT into events (title, date) VALUES ('$title', '$date')";

  if(!$result = $mysqli->query($sql)) {
    $response_array['message'] = "Error creating event";
//    echo "Query: " . $sql . "\n";
//    echo "Errno: " . $mysqli->errno . "\n";
//    echo "Error: " . $mysqli->error . "\n";
  } else {
    $response_array['message'] = "Success!";
  }
  $response_array['success'] = $result;

  $response_array['data']['eventId'] = mysqli_insert_id($mysqli);

  $mysqli->close();

} else if ($data['action'] == "update_event") {
  $title = mysqli_real_escape_string($mysqli, $data['title']);
  $date = mysqli_real_escape_string($mysqli, date("Y-m-d", strtotime($data['date'])));
  $eventId = mysqli_real_escape_string($mysqli, $data['eventId']);

  $sql = "UPDATE events SET title = '$title', date = '$date' WHERE ID = '$eventId'";

  if(!$result = $mysqli->query($sql)) {
    $response_array['message'] = "Error updating event";
    echo "Query: " . $sql . "\n";
    echo "Errno: " . $mysqli->errno . "\n";
    echo "Error: " . $mysqli->error . "\n";
  } else {
    $response_array['message'] = "Success!";
  }
  $response_array['success'] = $result;
  $response_array['data']['eventId'] = $eventId;

  $mysqli->close();

} else {
  $response_array['success'] = false;
  $response_array['message'] = "Post did not meet minimum requirements";
}

echo json_encode($response_array);  

?>
