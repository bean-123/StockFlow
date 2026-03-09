<?php

/**
 * Stock Movement Routes
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 3: Date/time recording for stock movements
 * (Dashboard analytics are in dashboard.php — Exercise 7)
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/stock/movements — List stock movements (authenticated)
// ============================================================
// EXERCISE 3 (Step 2): Students build this route
//
// Stock movements track inventory changes (in, out, adjustment).
// Each movement has a timestamp — this is where date/time matters most.
//
// Hints:
//   - Query the stock_movements table
//   - Join with products: 'select' => '*,products(name,sku)'
//   - Sort by newest first: 'order' => 'created_at.desc'
//   - Post-process: format dates, add relative time
//   - Optional filter: ?product_id=uuid to see movements for one product
// ============================================================

// STUB: Returns empty array until students implement Exercise 3 (Step 2).
// Replace the body of this route with your own logic.
$app->get('/api/stock/movements', function (Request $request, Response $response) {

    // TODO: Replace this with real data from Supabase
    //
    // $auth = new SupabaseAuth();
    // $auth->setToken($request->getAttribute('token'));
    //
    // TODO: Read optional product_id filter from query params
    // TODO: Build query with filters
    // TODO: Post-process dates
    // TODO: Return as JSON

    $response->getBody()->write(json_encode([]));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/stock/movements — Record a stock movement (authenticated)
// ============================================================
// EXERCISE 3 (Step 3): Students build this route
//
// When stock moves in or out, we record it AND update the product's stock_quantity.
// This is a two-step operation:
//   1. Insert the movement record
//   2. Update the product's stock_quantity
//
// The frontend sends:
//   {
//     product_id: "uuid",
//     quantity: 10,
//     movement_type: "in",       // "in", "out", or "adjustment"
//     reason: "Supplier delivery",
//     notes: "Invoice #12345"
//   }
//
// EXERCISE 3 focus: The created_at timestamp is auto-set by the database.
// But if you needed to record a movement for a past date, you could send:
//   'created_at' => date('c', strtotime('2026-03-01'))  // ISO 8601 format
//
// Hints:
//   - Validate: product_id, quantity (> 0), movement_type (in/out/adjustment)
//   - For "out" movements, check that enough stock exists
//   - Calculate new stock: for "in" add, for "out" subtract, for "adjustment" set directly
//   - Update the product's stock_quantity after inserting the movement
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 3 (Step 3).
// Replace the body of this route with your own logic.
$app->post('/api/stock/movements', function (Request $request, Response $response) {

    // $body = $request->getParsedBody();
    //
    // --- PRE-PROCESSING ---
    // TODO: Validate required fields
    // TODO: Check movement_type is valid
    // TODO: For "out" type, verify enough stock exists
    //
    // --- INSERT MOVEMENT ---
    // $auth = new SupabaseAuth();
    // $auth->setToken($request->getAttribute('token'));
    //
    // TODO: Insert into stock_movements table
    // TODO: Fetch current product stock_quantity
    // TODO: Calculate new quantity based on movement_type
    // TODO: Update product's stock_quantity
    //
    // --- POST-PROCESSING ---
    // TODO: Return the movement and updated stock level

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 3: POST /api/stock/movements is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
