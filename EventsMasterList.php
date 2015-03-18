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
    
    $field='title'; 
    $sort='ASC';

    //When clicked sort Ascending, if clicked again sort descending 
    //If sorting value is ascending, then you will sort table by descending

    if(isset($_GET['sorting'])) 
    { 
        if($_GET['sorting']=='ASC') { 
            $sort='DESC'; 
        } 
        else { 
            $sort='ASC'; 
        } 
    }

    if($_GET['field']=='title') { 
        $field = "title"; 
    } 
    elseif($_GET['field']=='venue_name') { 
        $field = "venue_name"; 
    } 
    elseif($_GET['field']=='category') { 
        $field="category"; 
    } 
    elseif($_GET['field']=='start') { 
        $field="start"; 
    } 
    elseif($_GET['field']=='end') { 
        $field="end"; 
    } 
    elseif($_GET['field']=='user_login') { 
        $field="user_login"; 
    }

    $stmt = $conn->prepare("SELECT title, wp_aec_venue.venue_name, wp_aec_event_category.category , start, end, wp_users.user_login
	 FROM wp_aec_event 
	 Inner Join wp_aec_event_category on
	 wp_aec_event_category.id = wp_aec_event.category_id
	 Inner Join wp_users on 
	 wp_users.id = wp_aec_event.user_id
	 Inner Join wp_aec_venue on
	 wp_aec_venue.venue_id = wp_aec_event.venue_id
	 Order by $field $sort"); 
     $stmt->execute();

    //create table and headings

    echo'<table border="1">'; 
    echo'<th><a href="EventsMasterList.php?sorting='.$sort.'&field=title">Event</a></th> 
         <th><a href="EventsMasterList.php?sorting='.$sort.'&field=venue_name">Venue</a></th> 
         <th><a href="EventsMasterList.php?sorting='.$sort.'&field=category">Category</a></th> 
         <th><a href="EventsMasterList.php?sorting='.$sort.'&field=start">Start</a></th> 
         <th><a href="EventsMasterList.php?sorting='.$sort.'&field=end">End</a></th> 
         <th><a href="EventsMasterList.php?sorting='.$sort.'&field=user_login">Organizer</a></th>';

    //reads data from each column out of the sql statement

    foreach ($stmt as $row) { 
        echo'
        <tr>
            <td>'.$row['title'].'</td>
            <td>'.$row['venue_name'].'</td>
            <td>'.$row['category'].'</td> 
            <td>'.$row['start'].'</td>
            <td>'.$row['end'].'</td>
            <td>'.$row['user_login'].'</td>
        </tr>'; 
    } 
    echo'</table>';

?>
