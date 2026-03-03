<?php

require_once "auth/SupabaseAuth.php";
$auth = new SupabaseAuth();

// Fetch products from supabase
try {
    $products = $auth->query('products', [
        'select' => '*,categories(name)',
        'order' => 'name.asc'
    ]);
} catch (Exception $e) {
    $products = [];
    $error = $e->getMessage();
}

include "functions.php";
include "includes/header.php";
echo "<h1>Products Example</h1>";
?>

<section class="content">

<aside class="col-xs-4">
    <?php Navigation(); ?>
</aside>

<article class="main-content col-xs-8">
<h1>Products</h1>

<?php if ($auth->isLoggedIn()) : ?>
    <p style="color: green;">Logged in as <?php echo htmlspecialchars($auth->getCurrentUser()['email'] ?? 'Guest'); ?></p>
<?php else: ?>
    <p style="color: red;">You are not logged in - <a href="11-authentication.php">Login here</a></p>
<?php endif; ?>

<?php if ($error) : ?>
    <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
<?php elseif (!empty($products)): ?>

<!-- Display products -->
<table style="width: 100%; border-collapse: collapse;">
    <thead>
        <tr style="background-color: #F5F5F5;">
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Name</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">SKU</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Category</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Price</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Stock</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($products as $product) : ?>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;">
                <?php echo htmlspecialchars($product['name']); ?>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                <?php echo htmlspecialchars($product['sku']); ?>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                <?php echo htmlspecialchars($product['categories']['name']) ?? "-"; ?>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                <?php echo number_format($product['price'], 2); ?>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                <?php echo htmlspecialchars($product['stock_quantity']) ?? 0; ?>
                </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        <?php else: ?>
            <p>No Products found</p>
        <?php endif; ?>
    </article>
</section>

<?php include "includes/footer.php"; ?>

