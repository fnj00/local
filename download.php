<?php

if (!isset($_GET['num'], $_GET['file'])) {
    http_response_code(400);
    exit('Missing required parameters.');
}

$num = $_GET['num'];
$file = $_GET['file'];

if (!is_numeric($num)) {
    http_response_code(400);
    exit('Invalid event number.');
}

// Prevent path traversal and only allow expected filenames
$file = basename($file);

if (!preg_match('/^[A-Za-z0-9_-]+\.(jpg|jpeg|webp)$/i', $file)) {
    http_response_code(400);
    exit('Invalid filename.');
}

$path = __DIR__ . "/photos/$num/$file";

if (!file_exists($path) || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$mimeType = mime_content_type($path);
if ($mimeType === false) {
    $mimeType = 'application/octet-stream';
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($path);
exit;
?>
