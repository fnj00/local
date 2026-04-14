<?php
$num = $_GET['num'];
$file = $_GET['file'];

header('Content-type: octet/stream');
header('Content-disposition: attachment; filename='.$file.';');
header('Content-Length: '.filesize("photos/$num/$file"));
readfile("photos/$num/$file");
exit;
?>
