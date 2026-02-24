<?php
$error = null;
require_once "auth/SupabaseAuth.php";
$auth = new SupabaseAuth();

// Fetch products from supabase
try {
    $orders = $auth->query('orders', ['order' => 'created_at.desc']);
} catch (Exception $e) {
    $orders = [];
    $error = $e->getMessage();
}

include "functions.php";
include "includes/header.php";
echo "<h1>Orders Example</h1>";
?>

<section class="content">

<aside class="col-xs-4">
    <?php Navigation(); ?>
</aside>

<article class="main-content col-xs-8">
<h1>Orders</h1>

<?php if ($auth->isLoggedIn()) : ?>
    <p style="color: green;">Logged in as <?php echo htmlspecialchars($auth->getCurrentUser()['email'] ?? 'Guest'); ?></p>
<?php else: ?>
    <p style="color: red;">You are not logged in - <a href="11-authentication.php">Login here</a></p>
<?php endif; ?>

<?php if ($error) : ?>
    <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
<?php elseif (!empty($orders)): ?>

<!-- Display Orders -->
<table style="width: 100%; border-collapse: collapse;">
    <thead>
        <tr style="background-color: #F5F5F5;">
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Customer name</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Status</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Created at</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Total amount</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Stock</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($orders as $order) : ?>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;">
                <?php echo htmlspecialchars($order['customer_name']); ?>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                <?php echo htmlspecialchars($order['status']); ?>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                <?php echo date('d.m.Y H:i:s', strtotime($order['created_at'])); ?>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                <?php echo number_format($order['total_amount'], 2); ?>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                <?php echo htmlspecialchars($order['notes']) ?? 0; ?>
                </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        <?php else: ?>
            <p>No Orders found</p>
        <?php endif; ?>
    </article>
</section>

<?php include "includes/footer.php"; ?>