<?php
	if(isset($_POST['submit']))
	{

		$conn = mysqli_connect("localhost","root","","wordpress_b") or die (mysql_error());


		$filename = 'uploads/'.strtotime("now").'.csv';
		$fp = fopen($filename, "w");

		$sql = mysqli_query($conn,"SELECT * FROM wordpress_bwp_aec_venue") or die (mysql_error());
		$num_rows = mysqli_num_rows($sql);

		if ($num_rows >= 1) {
		$row = mysqli_fetch_assoc($sql);

		$seperator = "";
		$comma = "";

		foreach($row as $name => $value)
		{
			$seperator .= $comma . '' .str_replace('', '""', $name);
			$comma = ",";
		}

		$seperator .= "\n";

		echo $seperator;

		fputs($fp,$seperator);

		mysqli_data_seek($sql, 0);

		while($row = mysqli_fetch_assoc($sql))
		{

		$seperator = "";
		$comma = "";

		foreach($row as $name => $value)
		{
			$seperator .= $comma . '' .str_replace('', '""', $value);
			$comma = ",";
		}

		$seperator .= "\n";

		fputs($fp,$seperator);

		}

		fclose($fp);
		}
		else
		{
			echo "No data in the database!";
		}

	}
?>
<!DOCTYPE html>
	<head>
		<title>Export Page</title>
		<style type="text/css">
			#container {
				width: 400px;
				height: 400px;
			}

			#btnExport {
				font-size: 28px;
				font-family: Tahoma, Verdana;
			}
		</style>
	</head>
	<body>
		<div id="container">
			<form method="post" action="export.php">
				<input id="btnExport" type="submit" name="submit" value="Export to CSV">
			</form>
		</div>
	</body>
</html>