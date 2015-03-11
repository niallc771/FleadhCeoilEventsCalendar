<script type="text/javascript" src="jquery-latest.js"></script> 
<script type="text/javascript" src="jquery.tablesorter.js"></script> 


	<style type="text/css">
        table {
            border: none;
        }
        th,
        td {
            padding: 4px 16px;
            font-family: Times New Roman;
            text-align: left;
        }
        th {
            background-color: #C8C8C8;
        }
    </style>
<script>
//$(document).ready(function() 
    //{ 
	//put in window.onload
	//alert("hi");
	//var tbl = <?php echo json_encode($tbl)?>;
	//var hi = "hi";
	//alert (hi);
	//document.appendChild(tbl);
	
		//$("#evnt").tablesorter(); 
    //} 
//); 



</script>	
	
<?php

require_once 'config.php';

  $tbl = "<table id='myTable' style='border: solid 4px violet;'>
  <thead><tr><th id='evnt' style='background-color: violet; color: white;'>Event</th>
			<th style='background-color: violet; color: white;'>Venue</th>
			<th style='background-color: violet; color: white;'>Category</th>
			<th style='background-color: violet; color: white;'>Start</th>
			<th style='background-color: violet; color: white;'>Finish</th>
			<th style='background-color: violet; color: white;'>Organizer</th></tr><thead>";
	echo "$tbl";
	
	
class TableRows extends RecursiveIteratorIterator { 
     function __construct($it) { 
         parent::__construct($it, self::LEAVES_ONLY); 
     }

     function current() {
         return "<td style='width: 150px; border: 1px solid black;'>" . parent::current(). "</td>";
     }

     function beginChildren() { 
         echo "<tr>"; 
     } 

     function endChildren() { 
         echo "</tr>" . "\n";
     } 
} 
  
try {
     $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
     $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
     $stmt = $conn->prepare("SELECT title, wp_aec_venue.venue_name, wp_aec_event_category.category , start, end, wp_users.user_login
	 FROM wp_aec_event 
	 Inner Join wp_aec_event_category on
	 wp_aec_event_category.id = wp_aec_event.category_id
	 Inner Join wp_users on 
	 wp_users.id = wp_aec_event.user_id
	 Inner Join wp_aec_venue on
	 wp_aec_venue.venue_id = wp_aec_event.venue_id
	 Order by title"); 
     $stmt->execute();
	 
     // set the resulting array to associative
     $result = $stmt->setFetchMode(PDO::FETCH_ASSOC); 

     foreach(new TableRows(new RecursiveArrayIterator($stmt->fetchAll())) as $k=>$v) { 
         echo $v;
     }
}
catch(PDOException $e) {
     echo "Error: " . $e->getMessage();
}
$conn = null;
echo "</table>";
?>  
