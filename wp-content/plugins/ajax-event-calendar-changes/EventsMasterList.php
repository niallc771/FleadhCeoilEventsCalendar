<?php
    echo "<table style='border: solid 1px black;'>";
    echo "<tr><th>User</th><th>Event</th><th>Start</th><th>Finish</th><th>Venue</th></tr>";
    
    class TableRows extends RecursiveIteratorIterator { // RecursiveIteratorIterator can be used to iterate through recursive iterators 
         function __construct($display) { 
             parent::__construct($display, self::LEAVES_ONLY); 
         }
    
         function current() {
             return "<td style='width: 350px; border: 1px solid black;'>" . parent::current(). "</td>";
         }
    
         function beginChildren() { 
             echo "<tr>"; 
         } 
    
         function endChildren() { 
             echo "</tr>" . "\n";
         } 
    } 
    require_once 'config.php';
    
    try {
         $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);  // PDO represents a connection between PHP and a database server
         $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // PDO will throw a PDOException and set its properties to reflect the error code and error information which will  effectively blow up the code and find where the problem is
         $stmt = $conn->prepare("SELECT wp_aec_event.user_id, wp_aec_event.title, wp_aec_event.start, wp_aec_event.end, wp_aec_venue.venue_name
                                 FROM wp_aec_event
                                 INNER JOIN wp_aec_venue
                                 ON wp_aec_event.venue_id = wp_aec_venue.venue_id;");  // $stmt epresents a prepared statement
         $stmt->execute();
    
         // set the resulting array to associative
         $result = $stmt->setFetchMode(PDO::FETCH_ASSOC); // setFetchMode - Set the default fetch mode for this statement, Returns TRUE on success or FALSE on failure. 
    
         foreach(new TableRows(new RecursiveArrayIterator($stmt->fetchAll())) as $k=>$v) { // RecursiveArrayIterator - This iterator allows to unset and modify values and keys while iterating over Arrays and Objects in the same way as the ArrayIterator, which allows to unset and modify values and keys while iterating over Arrays and Objects. 
             echo $v;
         }
    }
    catch(PDOException $error) {
         echo "Error: " . $error->getMessage();
    }
    $conn = null;
    echo "</table>";
?>  