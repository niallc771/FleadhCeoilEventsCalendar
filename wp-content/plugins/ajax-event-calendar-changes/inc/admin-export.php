<?php
//header("Content-Type: application/csv");
//header("Content-Disposition: attachment;Filename=fleadh-events.csv");
$tablename = 'wp_aec_venue';
$outputcsv = NULL;
$conn = mysql_connect("localhost", "root", "");

mysql_select_db("wordpress_b",$conn);

$res = mysql_query("Show Columns From $tablename",$conn);



while($row = mysql_fetch_array($res)){
    $outputcsv.= $row['id'].',';
}  
$outputcsv = substr($outputcsv, 0, -1)." \n";


$res = mysql_query("Select * From $tablename",$conn);



while ($row = mysql_fetch_assoc($res)) {
    $outputcsv.= join(',', $row)." \n";
}

// Output all the events CSV
echo $outputcsv;

// Close the connection
mysql_close($conn);?>