<?php include "functions.php"; ?>
<?php include "includes/header.php";?>

	<section class="content">

	<aside class="col-xs-4">

		<?php Navigation();?>
			
		
	</aside><!--SIDEBAR-->


<article class="main-content col-xs-8">

	
	<?php  

/*  Step1: Define a function and make it return a calculation of 2 numbers

	Step 2: Make a function that passes parameters and call it using parameter values


 */

	// define a function that returns the sum of two numbers
	function sumTwoNumbers($number1, $number2) {
		return $number1 + $number2;
	}

	// call the function and display the result
	echo "The sum of 10 and 90 is ".sumTwoNumbers(10, 10);
	echo "<br>";

	// define a function that passes parameters and call it using parameter values
	function sumUsingParameters($number1, $number2) {
		return $number1 + $number2;
	}

	// call the function and display the result
	echo "The sum of 10 and 20 is ".sumUsingParameters(10, 20);
?>





</article><!--MAIN CONTENT-->


<?php include "includes/footer.php"; ?>