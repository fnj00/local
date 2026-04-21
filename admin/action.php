<?php  
include("../includes/db.php");

//action.php
$input = filter_input_array(INPUT_POST);

print_r($input);

if($input["action"] === 'edit')
{
 $query = "
 UPDATE events
 SET title = '".$input["title"]."', 
 date = '".$input["date"]."',
 collagex = '".$input["collagex"]."',
 collagey = '".$input["collagey"]."',
 numcollage = '".$input["numcollage"]."',
 autoapprove = '".$input["autoapprove"]."'
 WHERE ID = '".$input["ID"]."'
 ";

 if (!$mysqli->query($query)) {
  // Oh no! The query failed.
  printf("Errormessage: %s\n", $mysqli->error);      
  exit;
 }
}

if($input["action"] === 'delete')
{
 $query = "
 DELETE FROM events 
 WHERE ID = '".$input["ID"]."'
 ";

 if (!$mysqli->query($query)) {
  // Oh no! The query failed.
 printf("Errormessage: %s\n", $mysqli->error);       
 exit;
 }
}

echo json_encode($input);

?>
