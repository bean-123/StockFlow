<?php
/**
 * Quick test page to verify the API works before building the React frontend.
 * Run with: php -S localhost:8005 -t public/  (from the api/ directory)
 * Then open: http://localhost:8005/test.php
 *
 * This tests:
 * 1. Composer autoloading works
 * 2. .env is loaded correctly
 * 3. SupabaseAuth can generate a Google login URL
 * 4. SupabaseAuth can query Supabase (if you have a valid token)
 */

require __DIR__ . '/../vendor/autoload.php';

use StockFlow\Auth\SupabaseAuth;

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

header('Content-Type: text/html; charset=utf-8');

echo "<h1>StockFlow API - Test Page</h1>";

// Test 1: Environment variables loaded
echo "<h2>1. Environment Variables</h2>";
$tests = [
    'SUPABASE_URL' => !empty($_ENV['SUPABASE_URL']),
    'SUPABASE_ANON_KEY' => !empty($_ENV['SUPABASE_ANON_KEY']),
    'SITE_URL' => !empty($_ENV['SITE_URL']),
    'CLIENT_URL' => !empty($_ENV['CLIENT_URL']),
];
echo "<ul>";
foreach ($tests as $key => $ok) {
    $status = $ok ? '✅' : '❌';
    $value = $ok ? substr($_ENV[$key], 0, 30) . '...' : 'MISSING';
    echo "<li>$status <strong>$key</strong> = $value</li>";
}
echo "</ul>";

// Test 2: SupabaseAuth class loads
echo "<h2>2. SupabaseAuth Class</h2>";
try {
    $auth = new SupabaseAuth();
    echo "<p>✅ SupabaseAuth created successfully</p>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    die();
}

// Test 3: Google login URL
echo "<h2>3. Google OAuth URL</h2>";
$loginUrl = $auth->getGoogleSignInUrl();
echo "<p>✅ Generated login URL</p>";
echo "<p><a href='" . htmlspecialchars($loginUrl) . "' target='_blank'>Click here to test Google login</a></p>";
echo "<p><small>" . htmlspecialchars(substr($loginUrl, 0, 100)) . "...</small></p>";

// Test 4: Query Supabase (with token if provided)
echo "<h2>4. Supabase Query Test</h2>";
$token = $_GET['token'] ?? null;

if ($token) {
    $auth->setToken($token);
    echo "<h3>Products:</h3>";
    try {
        $products = $auth->query('products', [
            'select' => '*,categories(name)',
            'order' => 'name.asc'
        ]);
        echo "<p>✅ Got " . count($products) . " products</p>";
        echo "<pre>" . htmlspecialchars(json_encode($products, JSON_PRETTY_PRINT)) . "</pre>";
    } catch (Exception $e) {
        echo "<p>❌ Products error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "<h3>Orders:</h3>";
    try {
        $orders = $auth->query('orders', ['order' => 'created_at.desc']);
        echo "<p>✅ Got " . count($orders) . " orders</p>";
        echo "<pre>" . htmlspecialchars(json_encode($orders, JSON_PRETTY_PRINT)) . "</pre>";
    } catch (Exception $e) {
        echo "<p>❌ Orders error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p>No token provided. To test queries:</p>";
    echo "<ol>";
    echo "<li>Click the Google login link above</li>";
    echo "<li>After login, copy the <code>access_token</code> from the URL fragment</li>";
    echo "<li>Visit: <code>http://localhost:8005/test.php?token=YOUR_TOKEN_HERE</code></li>";
    echo "</ol>";
}

echo "<hr><p><small>Test page for development only — delete before deploying.</small></p>";