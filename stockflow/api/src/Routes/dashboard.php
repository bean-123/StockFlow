<?php

/**
 * Dashboard Routes
 *
 * EXERCISE 7: Aggregate data into dashboard summaries
 *
 * This is the capstone exercise — it combines everything:
 * pre-processing, date handling, and data aggregation.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/dashboard/summary — Dashboard overview (authenticated)
// ============================================================
// EXERCISE 7: Students build this route
//
// Fetch data from multiple tables and calculate summary statistics.
// This is ALL post-processing — the database gives you raw data,
// you crunch it in PHP before sending to the frontend.
//
// The frontend expects:
//   {
//     inventory: {
//       total_products: 18,
//       total_value: 12450.00,       // sum of (price * stock_quantity)
//       low_stock_count: 4,          // products where stock <= threshold
//       out_of_stock_count: 2        // products where stock = 0
//     },
//     orders: {
//       total_orders: 5,
//       by_status: {
//         draft: 1,
//         confirmed: 1,
//         fulfilled: 2,
//         cancelled: 1
//       },
//       total_revenue: 2602.00       // sum of fulfilled order totals
//     },
//     low_stock_products: [          // top 5 most urgent
//       { name: "...", stock_quantity: 2, reorder_threshold: 10 },
//       ...
//     ]
//   }
//
// Hints:
//   - Fetch all products: $auth->query('products', ['select' => '*'])
//   - Fetch all orders: $auth->query('orders', ['select' => '*'])
//   - Use PHP array functions to calculate:
//     array_filter() — filter arrays by condition
//     array_sum()    — sum values
//     array_map()    — transform arrays
//     count()        — count items
//     usort()        — sort arrays with custom comparison
//   - For total_value: loop products, sum up (price * stock_quantity)
//   - For low_stock: filter where stock_quantity <= reorder_threshold AND stock > 0
//   - For revenue: filter orders where status === 'fulfilled', then sum total_amount
// ============================================================

// STUB: Returns placeholder data until students implement Exercise 7.
// Replace the body of this route with your own logic.
$app->get('/api/dashboard/summary', function (Request $request, Response $response) {

    // TODO: Replace this placeholder with real data from Supabase
    //
    // $auth = new SupabaseAuth();
    // $auth->setToken($request->getAttribute('token'));
    //
    // TODO: Fetch products and orders from Supabase
    //
    // TODO: Calculate inventory stats
    // $totalValue = 0;
    // $lowStock = [];
    // $outOfStock = 0;
    // foreach ($products as $product) { ... }
    //
    // TODO: Calculate order stats
    // $ordersByStatus = ['draft' => 0, 'confirmed' => 0, ...];
    // $revenue = 0;
    // foreach ($orders as $order) { ... }
    //
    // TODO: Sort low stock products by urgency (lowest stock first)
    // usort($lowStock, function ($a, $b) {
    //     return $a['stock_quantity'] - $b['stock_quantity'];
    // });

    // Placeholder response — shows the structure students need to build
    $placeholder = [
        'inventory' => [
            'total_products' => 0,
            'total_value' => 0.00,
            'low_stock_count' => 0,
            'out_of_stock_count' => 0
        ],
        'orders' => [
            'total_orders' => 0,
            'by_status' => [
                'draft' => 0,
                'confirmed' => 0,
                'fulfilled' => 0,
                'cancelled' => 0
            ],
            'total_revenue' => 0.00
        ],
        'low_stock_products' => [],
        '_message' => 'Exercise 7: Replace this placeholder with real calculations!'
    ];

    $response->getBody()->write(json_encode($placeholder));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
