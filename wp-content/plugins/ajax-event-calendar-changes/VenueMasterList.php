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

<?php
echo "<table style='border: solid 4px violet;'>";
  echo "<tr><th style='background-color: violet; color: white;'>Venue</th><th style='background-color: violet; color: white;'>Street</th><th style='background-color: violet; color: white;'>City</th><th style='background-color: violet; color: white;'>State</th><th style='background-color: violet; color: white;'>Website</th></tr>";

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
require_once 'config.php';

try {
     $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
     $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
     $stmt = $conn->prepare("SELECT venue_name, street, city, state, website FROM wp_aec_venue Order By venue_name"); 
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