<?php

function debug_to_console( $data ) {
    $output = $data;
    if ( is_array( $output ) )
        $output = implode( ',', $output);

    echo "<script>console.log( 'Debug Objects: " . $output . "' );</script>";
}

include("../includes/db.php");

function image_fix_orientation($image, $filename) {
    $image = imagerotate($image, array_values([0, 0, 0, 180, 0, 0, -90, 0, 90])[@exif_read_data($filename)['Orientation'] ?: 0], 0);
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

$supported_image = array(
    'gif',
    'jpg',
    'jpeg',
    'png'
);

$response_array = array();

$headers = getallheaders();

$fbid = $headers['Meta-Fbid'];
$fullname = $headers['Meta-Fullname'];
$filename = $headers['Meta-Name'];
$host = $headers['Host'];
$eventid = $headers['Meta-Eventid'];

if(is_numeric($fbid) && is_numeric($eventid)) {
  $eventId = (int) $eventid;
  $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive

  if (!file_exists("../photos/$eventId")) {
    mkdir("../photos/$eventId", 0755, true);
  }

  if (!in_array($ext, $supported_image)) {
    $response_array['success'] = false;
    $response_array['message'] = "File is not in the permitted extension list";
    echo json_encode($response_array);
    exit;
  } else {
    $fileName = uniqid().".".$ext;
    $im = imagecreatefromstring(file_get_contents("php://input"));
    file_put_contents("../photos/$eventId/$fileName", file_get_contents("php://input"));
    error_log("Put");
    image_fix_orientation($im, "../photos/$eventId/$fileName");
    error_log("Fix");
    if ($ext == "gif") {
      imagegif($im, $_SERVER['DOCUMENT_ROOT'].'/photos/'.$eventId.'/'.$fileName);
    } else if ($ext == "jpg" || $ext == "jpeg") {
      imagejpeg($im, $_SERVER['DOCUMENT_ROOT'].'/photos/'.$eventId.'/'.$fileName);
    } else {
      imagepng($im, $_SERVER['DOCUMENT_ROOT'].'/photos/'.$eventId.'/'.$fileName);
    }
    error_log("Destroy");
    imagedestroy($im);
  }
  error_log("Test");

//  if (!file_exists("../photos/$eventId")) {
//    mkdir("../photos/$eventId", 0755, true);
//  }

//  if (file_put_contents("../photos/$eventId/$fileName", file_get_contents("php://input")) !== false) {
  if (file_exists("../photos/$eventId/$fileName")) {
    error_log("File Exists!");
    $FileName = mysqli_real_escape_string($mysqli, $fileName);
    $fullName = mysqli_real_escape_string($mysqli, $fullname);
    $eventId = (int) $eventid;
    $fbId = (int) $fbid;

    $sql = "INSERT into photos (eventId, approved, filename, uploadedBy, uploadedfbId) VALUES ('$eventId', '0', '$FileName', '$fullName', '$fbId')";

    if (!$result = $mysqli->query($sql)) {
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
}

debug_to_console($response_array);
exit;

?>
