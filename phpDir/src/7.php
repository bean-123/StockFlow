<?php include "functions.php"; ?>
<?php include "includes/header.php";?>
    

	<section class="content">

		<aside class="col-xs-4">

		<?php Navigation();?>
			
			
		</aside><!--SIDEBAR-->


	<article class="main-content col-xs-8">
	
	
	
	<?php  

	/*  Step 1 - Create a database in PHPmyadmin - done

		Step 2 - Create a table like the one from the lecture

		id: 1, name: "John", password: "123456"

		Step 3 - Insert some Data

		Step 4 - Connect to Database and read data

*/

	$host = 'db';
	$user = 'lionUser';
	$pass = 'lionPass';
	$db = 'lionDB';
	$conn = new mysqli($host, $user, $pass, $db);
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	} else {
		echo "Connected to MySQL server successfully!";
	}

	$sql = "SELECT * from users";
	$result = $conn->query($sql);
	while ($row = $result->fetch_assoc()) {
		echo "ID: " . $row["id"] . " - Name: " . $row["name"] . " - Password: " . $row["password"] . "<br>";
	}
	$conn->close();
	
	?>





</article><!--MAIN CONTENT-->

<?php include "includes/footer.php"; ?>
