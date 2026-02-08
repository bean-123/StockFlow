<?php include "functions.php"; ?>
<?php include "includes/header.php"; ?>

<section class="content">

  <aside class="col-xs-4">

    <?php Navigation(); ?>

  </aside>
  <!--SIDEBAR-->


  <article class="main-content col-xs-8">
  <?php $message = $_POST['message'] ?? ''; ?>

    <form action="6.php" method="post">
    <input type="text" name="message" placeholder="Enter a message">
    <input type="submit" name="submit" value="Submit">
  </form>
  
  
    <?php echo $message; ?>



  </article>
  <!--MAIN CONTENT-->
  <?php include "includes/footer.php"; ?>