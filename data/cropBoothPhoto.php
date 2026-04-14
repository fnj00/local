<?php

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

$img = $_POST['imgUrl'];
$aspect = $_POST['imgRatio'];

$im = imagecreatefromjpeg($img);

$origWidth = imagesx($im);
$origHeight = imagesy($im);

$origAspect = $origWidth / $origHeight;

$cropWidth = $origWidth;
$cropHeight =  $origHeight;

if ($origAspect > $aspect) {
  $cropWidth = $origHeight * $aspect;
} else if ($origAspect < $aspect) {
  $cropHeight = $origWidth / $aspect;
}

$cropX = ( $origWidth - $cropWidth ) * .5;
$cropY = ( $origHeight - $cropHeight ) * .5;

$im2 = imagecrop($im, ['x' => $cropX, 'y' => $cropY, 'width' => $cropWidth, 'height' => $cropHeight]);

if ($im2 !== FALSE) {
    imagejpeg($im2, '../html/media/image-cropped.jpg');
    imagedestroy($im2);
    unlink($img);
    $response_array['message'] = "Success!";
} else {
    $response_array['message'] = "Error resizing image!";
}

header('Content-type: application/json');
echo json_encode($response_array);
imagedestroy($im);

?>
