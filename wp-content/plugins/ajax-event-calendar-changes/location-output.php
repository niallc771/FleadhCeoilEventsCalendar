<?php
$venue_name=$_POST["venue-name"];
$street_name=$_POST["street-name"];
$city_name=$_POST["city-name"];
$state_name=$_POST["state-name"];
$zip_name=$_POST["zip-name"];
$website_name=$_POST["website-name"]; ?>

<!--Connect to the database-->
<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "wordpress_b";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully"; ?>

<!-- Insert the values into the database-->
<?php
$sql = "INSERT INTO wp_aec_venue (venue_name, street, city, state, zip, website)
VALUES ('$venue_name', '$street_name', '$city_name', '$state_name', '$zip_name', 

'$website_name')";

if ($conn->query($sql) === TRUE) {
echo "New record created successfully";
} else {
echo "Error: " . $sql . "<br>" . $conn->error;
}
?>

<?php
//Redirects to the specified page
//header("Location: http://localhost/wordpress/wp-admin/admin.php?page=aec_manage_venues/");
header("Location: http://localhost/wordpress/");
?>
