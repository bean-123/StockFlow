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

    // Exercise 6 Step 1: filter by status with query params
    $queryParams = $request->getQueryParams();
    $filter = [
        'order' => 'created_at.desc'
    ];
    if (!empty($queryParams['status'])) {
        $filter['status'] = 'eq.' . trim($queryParams['status']);
    }

    $orders = $auth->query('orders', $filter);

    // --- POST-PROCESSING (Exercise 3) ---
    $processed = array_map(function ($order) {
        $timestamp = isset($order['created_at']) ? strtotime($order['created_at']) : null;
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

        return array_merge($order, [
            'created_date' => $createdDate,
            'created_ago' => $createdAgo,
            'age_days' => $daysAgo,
            'total_amount' => number_format((float) ($order['total_amount'] ?? 0), 2, '.', '')
        ]);
    }, $orders ?? []);

    $response->getBody()->write(json_encode($processed));
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

    $body = $request->getParsedBody();
    $customerName = trim($body['customer_name'] ?? '');
    $notes = trim($body['notes'] ?? '');
    $items = $body['items'] ?? [];

    if (!$customerName) {
        $response->getBody()->write(json_encode(['error' => 'customer_name is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (!is_array($items) || count($items) === 0) {
        $response->getBody()->write(json_encode(['error' => 'items array must be provided and non-empty']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    foreach ($items as $item) {
        if (empty($item['product_id']) || !isset($item['quantity']) || !isset($item['unit_price'])) {
            $response->getBody()->write(json_encode(['error' => 'Each item must include product_id, quantity, and unit_price']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if ((int)$item['quantity'] <= 0 || (float)$item['unit_price'] < 0) {
            $response->getBody()->write(json_encode(['error' => 'Item quantity must be >0 and unit_price must be >=0']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Insert order with initial total 0
    $createdOrder = $auth->insert('orders', [
        'customer_name' => $customerName,
        'notes' => $notes,
        'status' => 'draft',
        'total_amount' => 0
    ]);

    if (empty($createdOrder) || empty($createdOrder[0]['id'])) {
        $response->getBody()->write(json_encode(['error' => 'Failed to create order']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $orderId = $createdOrder[0]['id'];
    $totalAmount = 0;

    foreach ($items as $item) {
        $quantity = (int)$item['quantity'];
        $unitPrice = (float)$item['unit_price'];
        $lineTotal = $quantity * $unitPrice;
        $totalAmount += $lineTotal;

        $auth->insert('order_items', [
            'order_id' => $orderId,
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'] ?? '',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal
        ]);
    }

    $auth->update('orders', 'id=eq.' . $orderId, ['total_amount' => $totalAmount]);

    $order = $auth->query('orders', ['id' => 'eq.' . $orderId]);
    $orderItems = $auth->query('order_items', ['order_id' => 'eq.' . $orderId]);

    $resultOrder = $order[0] ?? null;
    if ($resultOrder) {
        $resultOrder['items'] = $orderItems;
        $resultOrder['created_date'] = isset($resultOrder['created_at']) ? date('j M Y, H:i', strtotime($resultOrder['created_at'])) : null;
        $timestamp = isset($resultOrder['created_at']) ? strtotime($resultOrder['created_at']) : null;
        $daysAgo = $timestamp ? (int) floor((time() - $timestamp) / 86400) : null;
        $resultOrder['created_ago'] = $daysAgo === 0 ? 'Today' : ($daysAgo === 1 ? 'Yesterday' : ($daysAgo !== null ? $daysAgo . ' days ago' : null));
        $resultOrder['total_amount'] = number_format((float)$totalAmount, 2, '.', '');
    }

    $response->getBody()->write(json_encode($resultOrder));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

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

    $id = $args['id'];
    $body = $request->getParsedBody();
    $newStatus = trim($body['status'] ?? '');

    $allowed = ['draft', 'confirmed', 'fulfilled', 'cancelled'];
    if (!$id || !in_array($newStatus, $allowed, true)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid status or missing order id']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $orders = $auth->query('orders', ['id' => 'eq.' . $id]);
    if (empty($orders)) {
        $response->getBody()->write(json_encode(['error' => 'Order not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $currentStatus = $orders[0]['status'] ?? 'draft';

    $validTransitions = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['fulfilled', 'cancelled'],
        'fulfilled' => [],
        'cancelled' => []
    ];

    if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus], true)) {
        $response->getBody()->write(json_encode(['error' => "Cannot change status from $currentStatus to $newStatus"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth->update('orders', 'id=eq.' . $id, ['status' => $newStatus]);

    $updated = $auth->query('orders', ['id' => 'eq.' . $id]);
    $order = $updated[0] ?? [];

    $timestamp = isset($order['created_at']) ? strtotime($order['created_at']) : null;
    $order['created_date'] = $timestamp ? date('j M Y, H:i', $timestamp) : null;
    $daysAgo = $timestamp ? (int) floor((time() - $timestamp) / 86400) : null;
    $order['created_ago'] = $daysAgo === 0 ? 'Today' : ($daysAgo === 1 ? 'Yesterday' : ($daysAgo !== null ? $daysAgo . ' days ago' : null));
    $order['total_amount'] = number_format((float)($order['total_amount'] ?? 0), 2, '.', '');

    $response->getBody()->write(json_encode($order));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
