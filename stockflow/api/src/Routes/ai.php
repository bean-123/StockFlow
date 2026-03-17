<?php

/**
 * AI Routes — Gemini Integration
 *
 * EXERCISE 8: Use the GeminiAI class to add AI-powered features
 *
 * The GeminiAI class is already built (src/AI/GeminiAI.php).
 * Your job is to build the routes that USE it with real data.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\AI\GeminiAI;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// POST /api/ai/describe — Generate a product description
// ============================================================
// EXERCISE 6 (Step 1): Students build this route
//
// Given a product name and basic details, ask Gemini to write
// a short marketing description.
//
// The frontend sends:
//   { product_id: "uuid" }
//
// Your route should:
//   1. Fetch the product from Supabase (to get name, category, price)
//   2. Build a prompt like:
//      "Write a short product description (2-3 sentences) for: {name}.
//       Category: {category}. Price: {price} EUR."
//   3. Send the prompt to Gemini using $ai->ask($prompt)
//   4. Return the generated description
//
// Hints:
//   - Create the AI instance: $ai = new GeminiAI();
//   - Call it: $description = $ai->ask($prompt);
//   - Wrap in try/catch — AI calls can fail (rate limits, network issues)
// ============================================================

// POST /api/ai/describe — generate a product description with Gemini
$app->post('/api/ai/describe', function (Request $request, Response $response) {
    $body = $request->getParsedBody();
    $productId = trim((string) ($body['product_id'] ?? ''));

    if ($productId === '') {
        $response->getBody()->write(json_encode(['error' => 'product_id is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $auth = new SupabaseAuth();
        $auth->setToken($request->getAttribute('token'));

        $products = $auth->query('products', [
            'id' => 'eq.' . $productId,
            'select' => '*,categories(name)'
        ]);

        if (empty($products) || !isset($products[0])) {
            $response->getBody()->write(json_encode(['error' => 'Product not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $product = $products[0];
        $name = $product['name'] ?? 'Unnamed product';
        $category = '';

        if (!empty($product['categories'])) {
            if (is_array($product['categories']) && isset($product['categories'][0]['name'])) {
                $category = $product['categories'][0]['name'];
            } elseif (is_string($product['categories'])) {
                $category = $product['categories'];
            }
        }

        if ($category === '' && !empty($product['category_id'])) {
            $category = $product['category_id'];
        }

        $price = number_format((float) ($product['price'] ?? 0), 2, '.', '');
        $prompt = "Write a 2-3 sentence product description for: {$name}. Category: {$category}. Price: {$price} EUR.";

        $ai = new GeminiAI();
        $description = $ai->ask($prompt);

        $response->getBody()->write(json_encode(['description' => trim($description)]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $message = $e->getMessage();

        if (stripos($message, 'quota') !== false) {
            $fallbackValue = "This is a fallback product description due to Gemini quota limits. " .
                "The product is " . ($name ?? 'your selected item') . ". " .
                "Please enable billing or wait for quota refresh to use real AI results.";

            $response->getBody()->write(json_encode([
                'description' => $fallbackValue,
                'warning' => 'Gemini quota exceeded; fallback output used',
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['error' => 'Gemini request failed', 'details' => $message]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());


// ============================================================
// POST /api/ai/stock-advice — Get AI advice on stock levels
// ============================================================
// EXERCISE 6 (Step 2): Students build this route
//
// Fetch all products with low stock and ask Gemini for advice.
//
// Your route should:
//   1. Fetch products where stock_quantity <= reorder_threshold
//      (hint: you may need to fetch all products and filter in PHP,
//       or use Supabase filter syntax)
//   2. Build a prompt with the low-stock products list
//   3. Ask Gemini for reorder recommendations
//   4. Return the AI advice plus the product data
//
// Example prompt:
//   "These products are running low on stock. For each, suggest a
//    reorder quantity based on the current stock and threshold:
//    - Wireless Earbuds Pro: 5 in stock, threshold: 15
//    - USB-C Hub Pro: 2 in stock, threshold: 10
//    Give a brief recommendation for each."
// ============================================================

// POST /api/ai/stock-advice — get action items for low-stock products
$app->post('/api/ai/stock-advice', function (Request $request, Response $response) {
    try {
        $auth = new SupabaseAuth();
        $auth->setToken($request->getAttribute('token'));

        $products = $auth->query('products', ['select' => '*', 'order' => 'name.asc']);

        $lowStockProducts = array_values(array_filter($products, function ($product) {
            $stock = isset($product['stock_quantity']) ? (int) $product['stock_quantity'] : 0;
            $threshold = isset($product['reorder_threshold']) ? (int) $product['reorder_threshold'] : 0;
            return $stock <= $threshold;
        }));

        if (empty($lowStockProducts)) {
            $advice = 'All products are above reorder threshold. No immediate reorder is needed.';
            $response->getBody()->write(json_encode(['advice' => $advice, 'products' => []]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $lines = [];
        foreach ($lowStockProducts as $product) {
            $name = $product['name'] ?? 'Unknown item';
            $stock = isset($product['stock_quantity']) ? (int) $product['stock_quantity'] : 0;
            $threshold = isset($product['reorder_threshold']) ? (int) $product['reorder_threshold'] : 0;
            $lines[] = "- {$name}: {$stock} in stock, threshold: {$threshold}";
        }

        $prompt = "These products are running low on stock. For each, suggest a reorder quantity based on the current stock and threshold:\n" . implode("\n", $lines) . "\nProvide a brief recommendation for each.";

        $ai = new GeminiAI();
        $advice = $ai->ask($prompt);

        $response->getBody()->write(json_encode(['advice' => trim($advice), 'products' => $lowStockProducts]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $message = $e->getMessage();

        if (stripos($message, 'quota') !== false) {
            $fallbackAdvice = "Fallback stock advice: Some items are low. Consider restocking stock and updating reorder thresholds. " .
                "Gemini quota is exhausted, so this is a local fallback.";

            $response->getBody()->write(json_encode([
                'advice' => $fallbackAdvice,
                'products' => $lowStockProducts ?? [],
                'warning' => 'Gemini quota exceeded; fallback output used',
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['error' => 'Gemini or database request failed', 'details' => $message]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());


// ============================================================
// POST /api/ai/summarize-orders — Summarize recent orders
// ============================================================
// EXERCISE 6 (Step 3 — Stretch): Students build this route
//
// Fetch recent orders and ask Gemini to summarize trends.
//
// Your route should:
//   1. Fetch orders from the last 7 days
//   2. Build a prompt with order data (customer, total, status)
//   3. Ask Gemini to identify patterns and summarize
//   4. Return the summary
//
// This combines Exercise 3 (date handling) with Exercise 8 (AI).
// ============================================================

// POST /api/ai/summarize-orders — summarizing recent order trends with Gemini
$app->post('/api/ai/summarize-orders', function (Request $request, Response $response) {
    try {
        $auth = new SupabaseAuth();
        $auth->setToken($request->getAttribute('token'));

        $sevenDaysAgo = date('c', strtotime('-7 days'));
        // URL-encode '+00:00' timezone offset manually for PostgREST filter syntax.
        $sevenDaysAgoUrl = str_replace('+', '%2B', $sevenDaysAgo);
        $orders = $auth->query('orders', ['created_at' => 'gte.' . $sevenDaysAgoUrl, 'order' => 'created_at.desc']);

        if (empty($orders)) {
            $summary = 'No orders in the last 7 days to summarize.';
            $response->getBody()->write(json_encode(['summary' => $summary, 'orders' => []]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $lines = [];
        foreach ($orders as $order) {
            $customer = $order['customer_name'] ?? 'Unknown customer';
            $total = number_format((float) ($order['total_amount'] ?? 0), 2, '.', '');
            $status = $order['status'] ?? 'unknown';
            $createdAt = $order['created_at'] ?? 'unknown';
            $lines[] = "- {$customer}: €{$total}, status: {$status}, created: {$createdAt}";
        }

        $prompt = "Summarize key trends and recommendations from these orders over the last 7 days (total " . count($orders) . ").\n" . implode("\n", $lines) . "\nIdentify patterns in status, order amounts, and any actions we should take.";

        $ai = new GeminiAI();
        $summary = $ai->ask($prompt);

        $response->getBody()->write(json_encode(['summary' => trim($summary), 'orders' => $orders]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $message = $e->getMessage();

        if (stripos($message, 'quota') !== false) {
            $fallbackSummary = "Fallback summary: Gemini quota exceeded. Review the last seven days of orders for fast-moving products, " .
                "high revenue, and status changes.";

            $response->getBody()->write(json_encode([
                'summary' => $fallbackSummary,
                'orders' => $orders ?? [],
                'warning' => 'Gemini quota exceeded; fallback output used',
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['error' => 'Gemini or database request failed', 'details' => $message]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());
