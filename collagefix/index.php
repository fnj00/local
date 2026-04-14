<?php

?>
<html>
  <head>
    <link rel="stylesheet" type="text/css" href="bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="style.css">
  <script src="js/jquery.min.js"></script>
  <script src="js/qrcode.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
    <script type="text/javascript">
function popImage() {
  $('.pop').on('click', function() {
    $("#loaderDiv").show();
    var imgFile = $(this).attr('src');
    var eventNum = imgFile.split('/')[2];
    var fileName = imgFile.split('/')[3];
    $('.imagepreview').attr('src', $(this).attr('src'));
    document.getElementById("qrcode").innerHTML = "";
    new QRCode(document.getElementById("qrcode"), "http://local.joedeejay.com/download.php?num=" + eventNum + "&file=" + fileName);
    $('#imagemodal').modal('show');
    $("#loaderDiv").hide();
  });
};
    </script>

  </head>
  <div id="loaderDiv" style="display: none;">
  </div>
  <body bgcolor="black">
    <center>
    <div id="top">
      <p id="eventName"></p>
<!--      <p id="message">Connect your phone to the 'jdj-booth' wifi and visit local.joedeejay.com to request songs and add your photo</p>
-->
    </div>
<!--
      <p id="eventName"></p>
      <p id="eventDate"></p>
-->
    <div id="collage">
      <img id="mainimg" src="blank.jpg"/>
    </div>
    <div class="modal fade" id="imagemodal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-body" style="background-color: white">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
            <img src="" class="imagepreview" style="width: 100%;" >
            <h1>Download this image by connecting to <b>joedeejay-guest</b> WiFi and scanning the QR code below.</h1>
            <div id="qrcode" style="background-color: white"></div>
          </div>
        </div>
      </div>
    </div>
  </body>

  <script type="text/javascript" src="js/collage.js"></script>

</html>
