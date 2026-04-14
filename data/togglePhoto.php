<?php

include("../includes/db.php");
header('Content-type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if(is_numeric($data['id']) && is_numeric($data['approved']) && is_numeric($data['eventId'])) {
  $photoId = (int) $data['id'];
  $approved = (int) $data['approved'];
  $eventId = (int) $data['eventId'];

  if($approved != '1'){
    $photonum = '0';
  } else {

    $sql = "SELECT * FROM events where ID = $eventId";

    if($result = $mysqli->query($sql)) {
      $row = $result->fetch_assoc();
      $numcollage = $row['numcollage'];
      $result->free();
    }

    $collagemax = $numcollage + 1;

    $sql1 = "SELECT photonum FROM photos WHERE eventId = $eventId AND photonum > '0' AND photonum < $collagemax";

    if($result = $mysqli->query($sql1)) {
      if($result->num_rows < $numcollage){
      
        $regenerateNumber = true;
        $result->free();
        do {
          $photonum      = rand(1, $numcollage);
          $checkPhotoNum = "SELECT * FROM photos WHERE eventId = $eventId AND photonum = '$photonum'";
          $result      = $mysqli->query($checkPhotoNum);

          if ($result->num_rows == 0) {
            $regenerateNumber = false;
          }
        } while ($regenerateNumber);
      } else {
        $photonum = '0';
      }
    } else {
      $photonum = '0';
    }
  }

  $sql2 = "UPDATE photos SET approved = $approved, photonum = $photonum WHERE ID = $photoId";

  if(!$result = $mysqli->query($sql2)) {
    $response_array['message'] = "Error editing photo db";
//    echo "Query: " . $sql . "\n";
//    echo "Errno: " . $mysqli->errno . "\n";
//    echo "Error: " . $mysqli->error . "\n";
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
