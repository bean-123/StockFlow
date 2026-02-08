<?php include "functions.php"; ?>
<?php include "includes/header.php";?>

	<section class="content">

	<aside class="col-xs-4">

	<?php Navigation();?>
			
	</aside><!--SIDEBAR-->


<article class="main-content col-xs-8">

<?php  

/*  Step1: Make an if Statement with elseif and else to finally display string saying, I love PHP



	Step 2: Make a forloop  that displays 10 numbers


	Step 3 : Make a switch Statement that test againts one condition with 5 cases

 */

 //Step1: Make an if Statement with elseif and else to finally display string saying, I love PHP
 if ($number1 > $number2) {
	echo "I love PHP";
 } elseif ($number1 < $number2) {
	echo "I love PHP";
 } else {
	echo "I love PHP";
 }

	// set the value of number1 and number2
	$number1 = 50;
	$number2 = 20;

	// add the two variables and display the sum with echo
	echo "<p>The sum of ".$number1." and ".$number2." is </p>";
	echo "<p>".$number1 + $number2."</p>";

	echo "<br>";

	// make a for loop that displays 10 numbers
	for ($i = 0; $i < 10; $i++) {
		echo "<p>".$i."</p>";
	}
	echo "<br>";

	// make a switch statement that test against one condition with 5 cases
	switch ($number1) {
		case 10:
			echo "number1 is 10";
			break;
		case 20:
			echo "number1 is 20";
			break;
		case 30:
			echo "number1 is 30";
			break;
		case 40:
			echo "number1 is 40";
			break;
		case 50:
			echo "number1 is 50";
			break;
	}
?>






</article><!--MAIN CONTENT-->
	
<?php include "includes/footer.php"; ?>