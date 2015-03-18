<style type="text/css"> 
        table { 
            border: none; 
        }
    
        th, td { 
            padding: 4px 16px; 
            font-family: Times New Roman; 
            border: 1px solid transparent; 
        }
    
        td { 
            background-color: #FFCCFF; 
            text-align: left; 
        }
    
        th { 
            background-color: #FF33CC; 
            cursor: pointer; 
            text-align: center; 
        }
    
        tr:nth-child(even) td { 
            background: #FFCCFF; 
        }
    
        tr:nth-child(odd) td { 
            background: #FF99FF; 
        }
    
        tr:hover { 
            color: #FF0000; 
        } 
    </style>

<?php

    //variable shows an error as they are not populated until after the click
    
    error_reporting(0);
    
    require_once 'config.php';
    
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password); 
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    //by default 
    $field='venue_name'; 
    $sort='ASC';
    
    //When clicked sort Ascending, if clicked again sort descending 
    //If sorting value is ascending, then you will sort table by descending
    
    if(isset($_GET['sorting'])) { 
        if($_GET['sorting']=='ASC') { 
            $sort='DESC'; 
        } else { 
            $sort='ASC'; 
        } 
    }
    
    if($_GET['field']=='venue_name') { 
        $field = "venue_name"; 
    } 
    elseif($_GET['field']=='street') { 
        $field = "street"; 
    } 
    elseif($_GET['field']=='city') { 
        $field="city"; 
    } 
    elseif($_GET['field']=='state') { 
        $field="state"; 
    } 
    elseif($_GET['field']=='website') { 
        $field="website"; 
    }
    
    $stmt = $conn->prepare("SELECT venue_name, street, city, state, website FROM wp_aec_venue ORDER BY $field $sort"); 
    $stmt->execute();
    
    //create table and headings
    
    echo'<table border="1">'; 
    echo'<th><a href="VenueMasterList.php?sorting='.$sort.'&field=venue">Venue</a></th> 
         <th><a href="VenueMasterList.php?sorting='.$sort.'&field=street">Street</a></th> 
         <th><a href="VenueMasterList.php?sorting='.$sort.'&field=city">City</a></th> 
         <th><a href="VenueMasterList.php?sorting='.$sort.'&field=state">State</a></th> 
         <th><a href="VenueMasterList.php?sorting='.$sort.'&field=website">Website</a></th>';
    
    //reads data from each column out of the sql statement
    
    foreach ($stmt as $row) { 
        echo'
        <tr>
            <td>'.$row['venue_name'].'</td>
            <td>'.$row['street'].'</td>
            <td>'.$row['city'].'</td> 
            <td>'.$row['state'].'</td>
            <td>'.$row['website'].'</td>
        </tr>'; 
    } 
    echo'</table>';

?>