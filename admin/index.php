<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Events</title>
    <script src="js/jquery.min.js"></script>
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <script src="js/jquery.dataTables.min.js"></script>
    <script src="js/dataTables.bootstrap.min.js"></script>
    <link rel="stylesheet" href="css/dataTables.bootstrap.min.css" />
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.tabledit.min.js"></script>
  </head>
<body>
<?php

include("../includes/db.php");

$sql = "SELECT * from events";
if (!$result = $mysqli->query($sql)) {
  // Oh no! The query failed.
  echo "Sorry, the website is experiencing problems.";
  exit;
}
$fields_num = mysqli_num_fields($result);

echo "<h1>Table: {$table}</h1>";
echo "<table id='events' class='table table-bordered table-striped'><tr>";
// printing table headers
echo "<td>Upload Collage Photo</td>";
for($i=0; $i<$fields_num; $i++)
{
    $field = mysqli_fetch_field($result);
    echo "<td>{$field->name}</td>";
}
echo "</tr>\n";
// printing table rows
while($row = mysqli_fetch_row($result))
{
    echo "<tr>";

    echo "<td>
<form action='upload.php' method='post' enctype='multipart/form-data'>
<input type='file' name='fileToUpload' id='fileToUpload'>
<input type='hidden' name='eventId' id='eventId' value='$row[0]'>
<br>
<input type='submit' value='Upload Image' name='submit'>
</form>
</td>";

    // $row is array... foreach( .. ) puts every element
    // of $row to $cell variable
    foreach($row as $cell)
        echo "<td>$cell</td>";

    echo "</tr>\n";
}
echo "</table>";
mysqli_free_result($result);
?>
</body></html>
<script>
$(document).ready(function(){
    $('#events').Tabledit({
      url:'action.php',
      inputClass: 'form-control input-sm',
      toolbarClass: 'btn-toolbar',
      groupClass: 'btn-group btn-group-sm',
      dangerClass: 'danger',
      eventType: 'click',
      editButton: true,
      deleteButton: true,
      saveButton: true,
      restoreButton: false,
      buttons:{
       edit: {
        class: 'btn btn-sm btn-default',
        html: '<span class="glyphicon glyphicon-pencil"></span>',
        action: 'edit'
       },
       delete: {
        class: 'btn btn-sm btn-default',
        html: '<span class="glyphicon glyphicon-trash"></span>',
        action: 'delete'
        },
       save: {
        class: 'btn btn-sm btn-success',
        html: 'Save'
       }
      },
      columns:{
       identifier:[1, "ID"],
       editable:[[2, 'title'], [3, 'date'], [5, 'collagex'], [6, 'collagey'], [7, 'numcollage'], [10, 'autoapprove']]
      },

      onSuccess:function(data, textStatus, jqXHR)
      {
       if(data.action == 'delete')
       {
        $('#'+data.id).remove();
       }
      }
     });

});
</script>
