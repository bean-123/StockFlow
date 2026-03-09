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

// STUB: Returns "not implemented" until students implement Exercise 8 (Step 1).
// Replace the body of this route with your own logic.
$app->post('/api/ai/describe', function (Request $request, Response $response) {

    // $body = $request->getParsedBody();
    // $productId = $body['product_id'] ?? null;
    //
    // TODO: Validate product_id
    //
    // TODO: Fetch the product from Supabase
    // $auth = new SupabaseAuth();
    // $auth->setToken($request->getAttribute('token'));
    // $products = $auth->query('products', [
    //     'id' => 'eq.' . $productId,
    //     'select' => '*,categories(name)'
    // ]);
    //
    // TODO: Build a prompt using the product data
    // TODO: Send to Gemini and return the result
    //
    // try {
    //     $ai = new GeminiAI();
    //     $description = $ai->ask($prompt);
    //     ...return JSON response with the description
    // } catch (\Exception $e) {
    //     ...return 500 error with message
    // }

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 8: POST /api/ai/describe is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

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

// STUB: Returns "not implemented" until students implement Exercise 8 (Step 2).
$app->post('/api/ai/stock-advice', function (Request $request, Response $response) {

    // TODO: Fetch all products
    // TODO: Filter to only those with stock_quantity <= reorder_threshold
    // TODO: Build prompt with the low-stock items
    // TODO: Ask Gemini for advice
    // TODO: Return the advice and product data

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 8: POST /api/ai/stock-advice is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

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

// STUB: Returns "not implemented" until students implement Exercise 8 (Step 3).
$app->post('/api/ai/summarize-orders', function (Request $request, Response $response) {

    // TODO: Implement this route

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 8: POST /api/ai/summarize-orders is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
