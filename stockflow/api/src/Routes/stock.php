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

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $queryParams = $request->getQueryParams();
    $filter = [];
    if (!empty($queryParams['product_id'])) {
        $filter['product_id'] = 'eq.' . $queryParams['product_id'];
    }

    $query = array_merge([
        'select' => '*,products(name,sku)',
        'order' => 'created_at.desc'
    ], $filter);

    $movements = $auth->query('stock_movements', $query);

    $formatted = array_map(function ($row) {
        $timestamp = isset($row['created_at']) ? strtotime($row['created_at']) : null;
        $createdDate = $timestamp ? date('j M Y, H:i', $timestamp) : null;
        $daysAgo = $timestamp ? (int) floor((time() - $timestamp) / 86400) : null;

        if ($daysAgo === 0) {
            $createdAgo = 'Today';
        } elseif ($daysAgo === 1) {
            $createdAgo = 'Yesterday';
        } elseif ($daysAgo !== null) {
            $createdAgo = $daysAgo . ' days ago';
        } else {
            $createdAgo = null;
        }

        return array_merge($row, [
            'created_date' => $createdDate,
            'created_ago' => $createdAgo,
            'age_days' => $daysAgo,
            'product_name' => $row['products']['name'] ?? null,
            'product_sku' => $row['products']['sku'] ?? null
        ]);
    }, $movements ?? []);

    $response->getBody()->write(json_encode($formatted));
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

    $body = $request->getParsedBody();
    $productId = trim($body['product_id'] ?? '');
    $quantity = isset($body['quantity']) ? (int) $body['quantity'] : 0;
    $movementType = trim($body['movement_type'] ?? '');
    $reason = trim($body['reason'] ?? '');
    $notes = trim($body['notes'] ?? '');

    if (!$productId || $quantity <= 0) {
        $response->getBody()->write(json_encode(['error' => 'product_id and quantity (>0) are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $validTypes = ['in', 'out', 'adjustment'];
    if (!in_array($movementType, $validTypes, true)) {
        $response->getBody()->write(json_encode(['error' => 'movement_type must be one of: in, out, adjustment']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $products = $auth->query('products', ['id' => 'eq.' . $productId]);
    if (empty($products)) {
        $response->getBody()->write(json_encode(['error' => 'Product not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $currentStock = isset($products[0]['stock_quantity']) ? (int) $products[0]['stock_quantity'] : 0;
    $newStock = $currentStock;

    if ($movementType === 'in') {
        $newStock += $quantity;
    } elseif ($movementType === 'out') {
        if ($currentStock < $quantity) {
            $response->getBody()->write(json_encode(['error' => 'Insufficient stock for out movement']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $newStock -= $quantity;
    } else { // adjustment
        $newStock = $quantity;
    }

    $movement = $auth->insert('stock_movements', [
        'product_id' => $productId,
        'quantity' => $quantity,
        'movement_type' => $movementType,
        'reason' => $reason,
        'notes' => $notes
    ]);

    $auth->update('products', 'id=eq.' . $productId, ['stock_quantity' => $newStock]);

    $result = [
        'movement' => $movement[0] ?? null,
        'product_id' => $productId,
        'old_stock' => $currentStock,
        'new_stock' => $newStock
    ];

    $response->getBody()->write(json_encode($result));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
