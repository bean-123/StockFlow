<?php

/**
 * Orders Routes
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 3: Date/time handling (timestamps, relative dates)
 * - Exercise 6: CRUD operations for orders and order items
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/orders — List orders (authenticated)
// ============================================================
// Currently returns raw order data.
//
// EXERCISE 3: Add date/time post-processing:
//   - Format 'created_at' as a human-readable date (e.g., "9 Mar 2026, 14:30")
//   - Add a 'created_ago' field with relative time (e.g., "2 days ago")
//   - Add an 'age_days' field (number of days since creation)
//   - Format 'total_amount' as currency with 2 decimal places
//
// EXERCISE 5 (Step 1): Add filtering:
//   - Filter by status: ?status=confirmed
//   - Sort by date: ?sort=created_at&order=desc
//
// PHP date/time hints:
//   $timestamp = strtotime($row['created_at']);         // Parse ISO date to Unix timestamp
//   $formatted = date('j M Y, H:i', $timestamp);       // "9 Mar 2026, 14:30"
//   $daysAgo = floor((time() - $timestamp) / 86400);   // 86400 = seconds in a day
//
//   For relative time, you can build a simple helper:
//   if ($daysAgo === 0) return 'Today';
//   if ($daysAgo === 1) return 'Yesterday';
//   return $daysAgo . ' days ago';
// ============================================================

$app->get('/api/orders', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $orders = $auth->query('orders', [
        'order' => 'created_at.desc'
    ]);

    // --- POST-PROCESSING (Exercise 3) ---
    // TODO: Format dates and add computed time fields
    // TODO: Format total_amount as currency

    $response->getBody()->write(json_encode($orders));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());


// ============================================================
// GET /api/orders/{id} — Get single order with items (authenticated)
// ============================================================
// EXERCISE 5 (Step 2): Students build this route
//
// Hints:
//   - Fetch the order: query('orders', ['id' => 'eq.' . $id])
//   - Fetch its items: query('order_items', ['order_id' => 'eq.' . $id])
//   - Combine them: $order['items'] = $items
//   - Return 404 if order not found
//   - Apply the same date formatting from Exercise 3
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 6 (Step 2).
$app->get('/api/orders/{id}', function (Request $request, Response $response, array $args) {

    // $id = $args['id'];
    // $auth = new SupabaseAuth();
    // $auth->setToken($request->getAttribute('token'));
    //
    // TODO: Fetch order by ID
    // TODO: Fetch order_items for this order
    // TODO: Combine and return

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 6: GET /api/orders/{id} is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/orders — Create an order with items (authenticated)
// ============================================================
// EXERCISE 5 (Step 3): Students build this route
//
// This is the most complex exercise — creating an order involves:
//   1. Validate the order data (customer_name required)
//   2. Insert the order (without items first)
//   3. Loop through items and insert each one
//   4. Calculate the total_amount from the items
//   5. Update the order with the calculated total
//
// The frontend sends:
//   {
//     customer_name: "Company Oy",
//     notes: "Rush order",
//     items: [
//       { product_id: "uuid", product_name: "Widget", quantity: 3, unit_price: 29.99 },
//       { product_id: "uuid", product_name: "Gadget", quantity: 1, unit_price: 49.99 }
//     ]
//   }
//
// EXERCISE 3 (bonus): Record timestamps correctly:
//   - The database auto-sets created_at, but you should understand that
//     Supabase stores timestamps in UTC (TIMESTAMPTZ)
//   - When displaying, the frontend handles timezone conversion
//   - If you need to set a date manually in PHP: date('c') gives ISO 8601 format
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 6 (Step 3).
$app->post('/api/orders', function (Request $request, Response $response) {

    // $body = $request->getParsedBody();
    //
    // --- PRE-PROCESSING ---
    // TODO: Validate customer_name
    // TODO: Validate items array is not empty
    // TODO: Validate each item has product_id, quantity, unit_price
    //
    // --- CREATE THE ORDER ---
    // $auth = new SupabaseAuth();
    // $auth->setToken($request->getAttribute('token'));
    //
    // Step 1: Insert the order (total_amount = 0 for now)
    // $order = $auth->insert('orders', [
    //     'customer_name' => trim($body['customer_name']),
    //     'notes' => trim($body['notes'] ?? ''),
    //     'status' => 'draft',
    //     'total_amount' => 0
    // ]);
    // $orderId = $order[0]['id'];
    //
    // Step 2: Insert each item and calculate total
    // $totalAmount = 0;
    // foreach ($body['items'] as $item) {
    //     $lineTotal = $item['quantity'] * $item['unit_price'];
    //     $totalAmount += $lineTotal;
    //
    //     $auth->insert('order_items', [
    //         'order_id' => $orderId,
    //         'product_id' => $item['product_id'],
    //         'product_name' => $item['product_name'],
    //         'quantity' => (int)$item['quantity'],
    //         'unit_price' => (float)$item['unit_price'],
    //         'line_total' => $lineTotal
    //     ]);
    // }
    //
    // Step 3: Update the order with the calculated total
    // $auth->update('orders', 'id=eq.' . $orderId, [
    //     'total_amount' => $totalAmount
    // ]);
    //
    // --- POST-PROCESSING ---
    // TODO: Return the order with its items and 201 status

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 6: POST /api/orders is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// PUT /api/orders/{id}/status — Update order status (authenticated)
// ============================================================
// EXERCISE 5 (Step 4): Students build this route
//
// This teaches state machine logic — not every status transition is valid:
//   draft → confirmed → fulfilled
//   draft → cancelled
//   confirmed → cancelled
//
// Hints:
//   - Fetch the current order to check its current status
//   - Define valid transitions as an array:
//     $validTransitions = [
//         'draft' => ['confirmed', 'cancelled'],
//         'confirmed' => ['fulfilled', 'cancelled'],
//     ];
//   - Return 400 if the transition is not valid
//   - Use $auth->update() to change the status
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 6 (Step 4).
$app->put('/api/orders/{id}/status', function (Request $request, Response $response, array $args) {

    // $id = $args['id'];
    // $body = $request->getParsedBody();
    // $newStatus = $body['status'] ?? null;
    //
    // TODO: Validate that newStatus is one of: draft, confirmed, fulfilled, cancelled
    // TODO: Fetch current order and check current status
    // TODO: Check if transition is valid
    // TODO: Update and return result

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 6: PUT /api/orders/{id}/status is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
