<?php include "functions.php"; ?>
<?php include "includes/header.php"; ?>

<section class="content">

	<aside class="col-xs-4">

		<?php Navigation(); ?>


	</aside>
	<!--SIDEBAR-->


	<article class="main-content col-xs-8">


		<?php



		/* Step 1: Make 2 variables called number1 and number2 and set 1 to value 10 and the other 20:

		  Step 2: Add the two variables and display the sum with echo:


		  Step3: Make 2 Arrays with the same values, one regular and the other associative

		 
			 */

			// set the value of number1 and number2
			$number1 = 10;
			$number2 = 20;

			// add the two variables and display the sum with echo
			echo $number1 + $number2;

			// make a regular array with the values 10, 20, 30, 40, 50
			$array = array(10, 20, 30, 40, 50);
			echo "The array is ".implode(", ", $array);
			echo "<br>";
			echo "The sum of the array is ".array_sum($array);
			echo "<br>";

			// make an associative array with the values 10, 20, 30, 40, 50 and display the values
			$associativeArray = array("10" => "ten", "20" => "twenty", "30" => "thirty", "40" => "forty", "50" => "fifty");
			echo "The associative array is ".implode(", ", $associativeArray);
			echo "<br>";
			foreach ($associativeArray as $key => $value) {
				echo "The value of ".$key." is ".$value."<br>";
				echo "<br>";
			}
			echo "<br>";
			// Sum of the associative array using the values not the keys
			$sum = 0;
			foreach ($associativeArray as $key => $value) {
				$sum += intval($key);
			}
			echo "The sum of the associative array is ".$sum;
			echo "<br>";




		?>



	</article>
	<!--MAIN CONTENT-->

	<?php include "includes/footer.php"; ?>