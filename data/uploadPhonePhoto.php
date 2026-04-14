<?php

include("../includes/db.php");

function png2jpg($originalFile, $outputFile, $quality) {
    $image = imagecreatefrompng($originalFile);
    imagejpeg($image, $outputFile, $quality);
    imagedestroy($image);
}

if (!function_exists('getallheaders')) {
    function getallheaders() {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
    return $headers;
    }
}

$headers = getallheaders();

$remoteAddr = $_SERVER['REMOTE_ADDR'];
$eventid = $_POST['eventId'];
$img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '',$_POST['image']));

if(is_numeric($eventid)) {
  $eventId = (int) $eventid;

  $sql = "SELECT * FROM events where ID = $eventId";

  if($result = $mysqli->query($sql)) {
    $row = $result->fetch_assoc();
    $numcollage = $row['numcollage'];
    $autoapprove = $row['autoapprove'];
    $result->free();
  }

  if($autoapprove == '1') {

    $collagemax = $numcollage + 1;

    $sql1 = "SELECT photonum FROM photos WHERE eventId = $eventId AND photonum > '0' AND photonum < $collagemax AND approved = '1'";

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
  } else {
    $photonum = '0';
  }

  if (!file_exists("../photos/$eventId")) {
    mkdir("../photos/$eventId", 0755, true);
  }

  $fileName = uniqid();
  file_put_contents("../photos/$eventId/$fileName.png", $img);
  png2jpg("../photos/$eventId/$fileName.png", "../photos/$eventId/$fileName.jpg", "70");

  if (file_exists("../photos/$eventId/$fileName.jpg")) {
    error_log("File Exists!");
    $FileName = mysqli_real_escape_string($mysqli, "$fileName");
    $eventId = (int) $eventid;

    $sql2 = "INSERT into photos (eventId, approved, filename, ip, photonum) VALUES ('$eventId', '$autoapprove', '$FileName', '$remoteAddr', '$photonum')";

    if (!$result = $mysqli->query($sql2)) {
      $response_array['success'] = $result;
      $response_array['message'] = "Error running photo insert query";
      error_log("Error running photo insert query");
    } else {
      $response_array['message'] = "Success!";
      error_log("Mysqli success");
    }
    $response_array['success'] = $result;
    $mysqli->close();
  } else {
    $response_array['message'] = "File at $eventId/$fileName not found!";
    error_log("File at $eventId/$fileName not found!");
  }
  header('Content-type: application/json');
  echo json_encode($response_array);
}

exit;

?>
