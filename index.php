<?php

?>
<html>
  <head>
    <link rel="stylesheet" type="text/css" href="style.css">
  </head>
  <body bgcolor="black">
    <center>
    <div id="top">
      <p id="eventName"></p>
<!--      <p id="message">Use this booth or connect your phone to the 'jdj-booth' wifi and visit local.joedeejay.com to request songs and add your photo</p>
-->
    </div>
<!--
      <p id="eventName"></p>
      <p id="eventDate"></p>
-->
    <div id="collage">
      <img id="mainimg" src="blank.jpg"/>
    </div>
  </body>

  <script src="js/jquery.min.js"></script>
  <script type="text/javascript" src="js/collage.js"></script>
  <script src="js/html2canvas.js"></script>
</html>
<script type="text/javascript">
$("#eventName").click(function() {

  html2canvas($("#collage")[0], {
    imageTimeout: 0
  }).then(function(canvas) {
    saveAs(canvas.toDataURL(), 'mosaic.png');
//    document.body.appendChild(canvas);
//    Canvas2Image.saveAsPNG(canvas);
//    $("#img-out").append(canvas);
  });

});

function saveAs(uri, filename) {
  var link = document.createElement('a');
  if (typeof link.download === 'string') {
    link.href = uri;
    link.download = filename;

    //Firefox requires the link to be in the body
    document.body.appendChild(link);

    //simulate click
    link.click();

    //remove the link when done
    document.body.removeChild(link);
  } else {
    window.open(uri);
  }
}
</script>
