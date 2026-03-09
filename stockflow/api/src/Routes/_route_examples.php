<?php

/**
 * ============================================================
 * ROUTE EXAMPLES — Reference Guide for StockFlow API
 * ============================================================
 *
 * This file is NOT loaded by the app. It's a reference for how to
 * build each type of API route in Slim 4 with SupabaseAuth.
 *
 * IMPORTANT CONCEPTS:
 * - Every route receives a Request and must return a Response
 * - $request  = what the client sent (URL, headers, body, query params)
 * - $response = what we send back (JSON data, status code)
 *
 * THE DATA FLOW:
 * ┌─────────┐     ┌──────────┐     ┌──────────────┐     ┌──────────┐
 * │ Browser │ ──► │ PHP Route│ ──► │ Supabase DB  │ ──► │ PHP Route│ ──► Browser
 * │ (React) │     │          │     │              │     │          │
 * │         │     │ 1. Validate    │ 3. Query     │     │ 4. Format│
 * │         │     │ 2. Prepare     │    data      │     │ 5. Return│
 * └─────────┘     └──────────┘     └──────────────┘     └──────────┘
 *
 * WHERE DOES YOUR CODE GO?
 *
 * PRE-PROCESSING (before sending to Supabase):
 *   - Validate input (check required fields, correct types)
 *   - Sanitize data (trim strings, escape special characters)
 *   - Build query parameters (filters, sorting, pagination)
 *   - Check permissions (does this user have the right role?)
 *
 * POST-PROCESSING (after getting data from Supabase):
 *   - Format data for the frontend (dates, prices, labels)
 *   - Calculate derived fields (totals, averages, status labels)
 *   - Group or restructure data (group by category, nest items)
 *   - Filter sensitive fields (remove internal IDs, tokens)
 *
 * ============================================================
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// EXAMPLE 1: READ (GET) — Public route, no auth required
// ============================================================
// Use GET when: fetching data without changing anything
// No AuthMiddleware needed — anyone can access this
//
// Frontend calls: fetch('/api/example')
// ============================================================

$app->get('/api/example', function (Request $request, Response $response) {

    // --- PRE-PROCESSING ---
    // Get query parameters from the URL (e.g., /api/example?status=active&page=1)
    $params = $request->getQueryParams();

    // Read individual query params with defaults
    $status = $params['status'] ?? 'active';  // Default to 'active' if not provided
    $page = (int)($params['page'] ?? 1);      // Cast to integer for safety
    $limit = (int)($params['limit'] ?? 20);   // How many items per page

    // Build the Supabase query
    // The 'select' param tells Supabase which columns to return
    // You can also join related tables: '*,categories(name)' joins the category name
    $queryParams = [
        'select' => '*,categories(name)',
        'status' => 'eq.' . $status,           // Supabase filter syntax
        'order' => 'name.asc',                 // Sort by name ascending
        'limit' => $limit,
        'offset' => ($page - 1) * $limit       // Skip items for pagination
    ];

    // --- QUERY SUPABASE ---
    $auth = new SupabaseAuth();
    $items = $auth->query('products', $queryParams);

    // --- POST-PROCESSING ---
    // Transform the raw database data before sending to the frontend
    $processed = array_map(function ($item) {

        // Calculate stock status using if/elseif — clearer than a nested ternary
        if ($item['stock_quantity'] <= 0) {
            $stockStatus = 'out_of_stock';
        } elseif ($item['stock_quantity'] <= $item['reorder_threshold']) {
            $stockStatus = 'low_stock';
        } else {
            $stockStatus = 'in_stock';
        }

        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'price' => number_format((float)$item['price'], 2),  // "129.00"
            'category' => $item['categories']['name'] ?? 'Uncategorized',
            'stock_status' => $stockStatus,
        ];
    }, $items);

    // --- RETURN RESPONSE ---
    // Always return JSON with a clear structure
    $response->getBody()->write(json_encode([
        'data' => $processed,
        'page' => $page,
        'limit' => $limit
    ]));

    return $response->withHeader('Content-Type', 'application/json');
});


// ============================================================
// EXAMPLE 2: CREATE (POST) — Protected route, auth required
// ============================================================
// Use POST when: creating a new record
// AuthMiddleware checks for Bearer token in the Authorization header
// The token is then available via $request->getAttribute('token')
//
// Frontend calls:
//   fetch('/api/example', {
//     method: 'POST',
//     headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ...' },
//     body: JSON.stringify({ name: 'New Item', price: 29.99 })
//   })
// ============================================================

$app->post('/api/example', function (Request $request, Response $response) {

    // --- PRE-PROCESSING ---
    // Get the JSON body the client sent
    $body = $request->getParsedBody();

    // Validate required fields — return 400 (Bad Request) if missing
    if (empty($body['name'])) {
        $response->getBody()->write(json_encode([
            'error' => 'Name is required'
        ]));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    // Sanitize and prepare data for the database
    $data = [
        'name' => trim($body['name']),                       // Remove whitespace
        'price' => (float)($body['price'] ?? 0),             // Cast to number
        'description' => trim($body['description'] ?? ''),   // Optional field
        'status' => 'active',                                // Set default status
    ];

    // --- QUERY SUPABASE ---
    // Set the token so Supabase knows who is making this request
    // This is important for RLS (Row Level Security) policies
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // insert() sends a POST to Supabase and returns the created row
    $created = $auth->insert('products', $data);

    // --- POST-PROCESSING ---
    // Return the created item with a 201 (Created) status
    $response->getBody()->write(json_encode([
        'message' => 'Product created successfully',
        'data' => $created
    ]));

    return $response
        ->withStatus(201)
        ->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());  // <-- This line protects the route


// ============================================================
// EXAMPLE 3: UPDATE (PUT) — Protected route with URL parameter
// ============================================================
// Use PUT when: updating an existing record
// The {id} in the URL becomes available as $args['id']
//
// Frontend calls:
//   fetch('/api/example/some-uuid-here', {
//     method: 'PUT',
//     headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ...' },
//     body: JSON.stringify({ name: 'Updated Name', price: 39.99 })
//   })
// ============================================================

$app->put('/api/example/{id}', function (Request $request, Response $response, array $args) {

    // --- PRE-PROCESSING ---
    // $args['id'] comes from the URL: /api/example/{id}
    $id = $args['id'];
    $body = $request->getParsedBody();

    // Build only the fields that were actually sent
    // This way the client can update just one field without sending everything
    $data = [];
    if (isset($body['name']))        $data['name'] = trim($body['name']);
    if (isset($body['price']))       $data['price'] = (float)$body['price'];
    if (isset($body['description'])) $data['description'] = trim($body['description']);
    if (isset($body['status']))      $data['status'] = $body['status'];

    // Nothing to update?
    if (empty($data)) {
        $response->getBody()->write(json_encode([
            'error' => 'No fields to update'
        ]));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    // --- QUERY SUPABASE ---
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // update() uses PATCH under the hood — it only changes the fields you send.
    // The filter 'id=eq.' . $id tells Supabase which row to update.
    $updated = $auth->update('products', 'id=eq.' . $id, $data);

    // --- POST-PROCESSING ---
    $response->getBody()->write(json_encode([
        'message' => 'Product updated successfully',
        'data' => $updated
    ]));

    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// EXAMPLE 4: DELETE — Protected route with URL parameter
// ============================================================
// Use DELETE when: removing a record
// Be careful with deletes! Consider using "soft delete" (set status='archived')
// instead of actually removing data.
//
// Frontend calls:
//   fetch('/api/example/some-uuid-here', {
//     method: 'DELETE',
//     headers: { Authorization: 'Bearer ...' }
//   })
// ============================================================

$app->delete('/api/example/{id}', function (Request $request, Response $response, array $args) {

    // --- PRE-PROCESSING ---
    $id = $args['id'];

    // Validate that id looks like a UUID (basic check)
    if (strlen($id) < 30) {
        $response->getBody()->write(json_encode([
            'error' => 'Invalid ID format'
        ]));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    // --- QUERY SUPABASE ---
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // delete() sends a DELETE to Supabase
    // The filter uses Supabase syntax: column=eq.value
    $auth->delete('products', 'id=eq.' . $id);

    // --- POST-PROCESSING ---
    // Return 200 with a confirmation message
    // Some APIs return 204 (No Content) for deletes — either is fine
    $response->getBody()->write(json_encode([
        'message' => 'Product deleted successfully'
    ]));

    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// QUICK REFERENCE: Common Supabase Filter Syntax
// ============================================================
//
// Equals:           'column' => 'eq.value'
// Not equals:       'column' => 'neq.value'
// Greater than:     'column' => 'gt.100'
// Less than:        'column' => 'lt.50'
// Greater or equal: 'column' => 'gte.10'
// Less or equal:    'column' => 'lte.99'
// Like (pattern):   'column' => 'like.%search%'
// Case-insensitive: 'column' => 'ilike.%search%'
// In list:          'column' => 'in.(a,b,c)'
// Order:            'order' => 'column.asc' or 'column.desc'
// Limit:            'limit' => 20
// Offset:           'offset' => 40
// Select columns:   'select' => 'id,name,price'
// Join table:       'select' => '*,categories(name)'
//
// ============================================================
