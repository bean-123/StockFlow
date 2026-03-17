<?php

/**
 * Products Routes
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 1: Pre-process product data (stock status, formatted prices)
 * - Exercise 2: Add search and filtering via query parameters
 * - Exercise 4: Full CRUD operations (create, update, delete)
 * - Exercise 5: Image upload to Supabase Storage
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/products — List products (public)
// ============================================================

$app->get('/api/products', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();

    $params = $request->getQueryParams();
    $search = trim((string) ($params['search'] ?? ''));
    $category = trim((string) ($params['category'] ?? ''));
    $status = trim((string) ($params['status'] ?? ''));
    $sort = trim((string) ($params['sort'] ?? 'name'));
    $order = strtolower(trim((string) ($params['order'] ?? 'asc')));
    $page = max(1, (int) ($params['page'] ?? 1));
    $limit = max(1, min(100, (int) ($params['limit'] ?? 10)));

    $allowedSortFields = ['name', 'price', 'stock_quantity', 'created_at'];
    if (!in_array($sort, $allowedSortFields)) {
        $sort = 'name';
    }
    $order = $order === 'desc' ? 'desc' : 'asc';

    // Load category id -> name map for post-processing and category filtering
    $categoryRows = $auth->query('categories', ['select' => 'id,name']);
    $categoryMap = [];
    foreach ($categoryRows as $cat) {
        $categoryMap[$cat['id']] = $cat['name'];
    }

    // Fallback static mapping in case categories are locked by RLS or not populated
    if (empty($categoryMap)) {
        $categoryMap = [
            'c7753ecb-e269-4b17-8aab-24a6f3ccf1c2' => 'Audio',
            '6004037b-0b53-44f2-b5cb-5cbae9067b9f' => 'Cables & Adapters',
            'f10014a7-4026-4007-9088-e12e30d6b4dc' => 'Displays',
            '91a5379a-42c7-4f94-86a8-c14ec0ce436f' => 'Keyboards',
            'a73f7b22-d019-4b51-985a-06b3d78e39ab' => 'Mice & Peripherals',
            '35799a38-1112-4c32-89c1-1f68c5051179' => 'Power & Charging',
        ];
    }

    $queryParams = [
        'select' => '*',
        'order' => $sort . '.' . $order,
        'limit' => $limit + 1,
        'offset' => ($page - 1) * $limit,
    ];

    if ($search !== '') {
        $queryParams['name'] = 'ilike.*' . $search . '*';
    }

    if ($status !== '') {
        $queryParams['status'] = 'eq.' . $status;
    }

    if ($category !== '') {
        $matchedCategoryId = null;
        foreach ($categoryMap as $id => $name) {
            if (strcasecmp(trim($name), $category) === 0) {
                $matchedCategoryId = $id;
                break;
            }
        }

        if ($matchedCategoryId) {
            $queryParams['category_id'] = 'eq.' . $matchedCategoryId;
        } else {
            $response->getBody()->write(json_encode([]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    $products = $auth->query('products', $queryParams);

    $processed = array_map(function ($product) use ($categoryMap) {
        $stockQuantity = (int) ($product['stock_quantity'] ?? 0);
        $reorderThreshold = (int) ($product['reorder_threshold'] ?? 0);

        if ($stockQuantity <= 0) {
            $stockStatus = 'out_of_stock';
        } elseif ($stockQuantity <= $reorderThreshold) {
            $stockStatus = 'low_stock';
        } else {
            $stockStatus = 'in_stock';
        }

        $categoryId = $product['category_id'] ?? null;
        $categoryName = $categoryId && isset($categoryMap[$categoryId])
            ? $categoryMap[$categoryId]
            : 'Uncategorized';

        return [
            'id' => $product['id'] ?? null,
            'name' => $product['name'] ?? '',
            'sku' => $product['sku'] ?? '',
            'price' => number_format((float) ($product['price'] ?? 0), 2, '.', ''),
            'description' => $product['description'] ?? '',
            'stock_quantity' => $stockQuantity,
            'stock_status' => $stockStatus,
            'category_name' => $categoryName,
            'category_id' => $categoryId,
            'image_url' => $product['image_url'] ?? null,
            'status' => $product['status'] ?? null,
        ];
    }, $products);

    $hasNext = count($processed) > $limit;
    if ($hasNext) {
        $processed = array_slice($processed, 0, $limit);
    }

    $payload = [
        'data' => $processed,
        'page' => $page,
        'limit' => $limit,
        'hasNext' => $hasNext,
        'hasPrev' => $page > 1,
    ];

    $response->getBody()->write(json_encode($payload));
    return $response->withHeader('Content-Type', 'application/json');
});


// ============================================================
// GET /api/categories — List categories (public)
// ============================================================
$app->get('/api/categories', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();

    $token = $request->getHeaderLine('Authorization');
    $token = str_replace('Bearer ', '', $token);
    if ($token) $auth->setToken($token);

    $defaultCategoryNames = [
        'Audio',
        'Cables & Adapters',
        'Displays',
        'Keyboards',
        'Mice & Peripherals',
        'Power & Charging',
    ];

    try {
        $categories = $auth->query('categories', ['select' => 'id,name', 'order' => 'name.asc']);
    } catch (Exception $e) {
        $categories = [];
    }

    if (empty($categories)) {
        foreach ($defaultCategoryNames as $name) {
            try {
                $auth->insert('categories', ['name' => $name]);
            } catch (Exception $e) {
                // Ignore duplicates or policy failures.
            }
        }

        try {
            $categories = $auth->query('categories', ['select' => 'id,name', 'order' => 'name.asc']);
        } catch (Exception $e) {
            $categories = [];
        }
    }

    // Fallback to real UUIDs if DB is still empty or blocked
    if (empty($categories)) {
        $categories = [
            ['id' => 'c7753ecb-e269-4b17-8aab-24a6f3ccf1c2', 'name' => 'Audio'],
            ['id' => '6004037b-0b53-44f2-b5cb-5cbae9067b9f', 'name' => 'Cables & Adapters'],
            ['id' => 'f10014a7-4026-4007-9088-e12e30d6b4dc', 'name' => 'Displays'],
            ['id' => '91a5379a-42c7-4f94-86a8-c14ec0ce436f', 'name' => 'Keyboards'],
            ['id' => 'a73f7b22-d019-4b51-985a-06b3d78e39ab', 'name' => 'Mice & Peripherals'],
            ['id' => '35799a38-1112-4c32-89c1-1f68c5051179', 'name' => 'Power & Charging'],
        ];
    }

    $response->getBody()->write(json_encode($categories));
    return $response->withHeader('Content-Type', 'application/json');
});


// ============================================================
// POST /api/categories/seed — Seed default categories (if missing)
// ============================================================
$app->post('/api/categories/seed', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();

    $defaultNames = [
        'Audio',
        'Cables & Adapters',
        'Displays',
        'Keyboards',
        'Mice & Peripherals',
        'Power & Charging',
    ];

    foreach ($defaultNames as $name) {
        try {
            $auth->insert('categories', ['name' => $name]);
        } catch (Exception $e) {
            // may fail due duplicate or RLS; ignore and continue
        }
    }

    $categories = $auth->query('categories', ['select' => 'id,name', 'order' => 'name.asc']);
    $response->getBody()->write(json_encode($categories));
    return $response->withHeader('Content-Type', 'application/json');
});


// ============================================================
// GET /api/products/{id} — Get single product (public)
// ============================================================
$app->get('/api/products/{id}', function (Request $request, Response $response, array $args) {

    $id = $args['id'];
    $auth = new SupabaseAuth();

    $products = $auth->query('products', [
        'id' => 'eq.' . $id,
        'select' => '*,categories(name)'
    ]);

    if (empty($products)) {
        $response->getBody()->write(json_encode(['error' => 'Product not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $product = $products[0];
    $stockQuantity = (int) ($product['stock_quantity'] ?? 0);
    $reorderThreshold = (int) ($product['reorder_threshold'] ?? 0);

    if ($stockQuantity <= 0) {
        $stockStatus = 'out_of_stock';
    } elseif ($stockQuantity <= $reorderThreshold) {
        $stockStatus = 'low_stock';
    } else {
        $stockStatus = 'in_stock';
    }

    $response->getBody()->write(json_encode([
        'id' => $product['id'],
        'name' => $product['name'] ?? '',
        'sku' => $product['sku'] ?? '',
        'price' => number_format((float) ($product['price'] ?? 0), 2, '.', ''),
        'description' => $product['description'] ?? '',
        'stock_quantity' => $stockQuantity,
        'stock_status' => $stockStatus,
        'category_name' => $product['categories']['name'] ?? 'Uncategorized',
        'category_id' => $product['category_id'] ?? null,
        'image_url' => $product['image_url'] ?? null,
        'status' => $product['status'] ?? null,
        'supplier' => $product['supplier'] ?? null,
        'reorder_threshold' => $product['reorder_threshold'] ?? null,
    ]));
    return $response->withHeader('Content-Type', 'application/json');

});


// ============================================================
// POST /api/products — Create a product (admin/manager only)
// ============================================================
$app->post('/api/products', function (Request $request, Response $response) {

    $body = $request->getParsedBody() ?? [];

    $name = trim((string) ($body['name'] ?? ''));
    $sku = trim((string) ($body['sku'] ?? ''));
    $priceRaw = $body['price'] ?? null;
    $price = is_numeric($priceRaw) ? (float) $priceRaw : null;

    if ($name === '' || $sku === '' || $price === null) {
        $response->getBody()->write(json_encode([
            'error' => 'Validation failed',
            'message' => 'name, sku, and price are required fields and must be valid.'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $data = [
        'name' => $name,
        'sku' => $sku,
        'price' => $price,
        'description' => trim((string) ($body['description'] ?? '')),
        'category_id' => trim((string) ($body['category_id'] ?? '')) ?: null,
        'image_url' => trim((string) ($body['image_url'] ?? '')) ?: null,
        'status' => trim((string) ($body['status'] ?? 'active')),
        'stock_quantity' => isset($body['stock_quantity']) ? (int) $body['stock_quantity'] : 0,
        'reorder_threshold' => isset($body['reorder_threshold']) ? (int) $body['reorder_threshold'] : 0,
        'supplier' => trim((string) ($body['supplier'] ?? '')) ?: null,
    ];

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Validate category_id exists in DB
    if (isset($data['category_id']) && $data['category_id'] !== null) {
        $categoryId = $data['category_id'];

        if (!preg_match('/^[0-9a-fA-F\-]{36}$/', $categoryId)) {
            unset($data['category_id']);
        } else {
            $existing = [];
            try {
                $existing = $auth->query('categories', ['select' => 'id,name', 'id' => 'eq.' . $categoryId]);
            } catch (Exception $e) {
                $existing = [];
            }

            if (empty($existing)) {
                unset($data['category_id']);
            }
        }
    }

    // Remove null category_id to avoid FK constraint issues
    if (array_key_exists('category_id', $data) && $data['category_id'] === null) {
        unset($data['category_id']);
    }

    try {
        $created = $auth->insert('products', $data);
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Unable to create product', 'details' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode($created));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// PUT /api/products/{id} — Update a product (admin/manager only)
// ============================================================
$app->put('/api/products/{id}', function (Request $request, Response $response, array $args) {

    $id = $args['id'];
    $body = $request->getParsedBody() ?? [];

    $allowedFields = [
        'name', 'sku', 'price', 'description', 'category_id', 'image_url', 'status', 'stock_quantity', 'reorder_threshold', 'supplier'
    ];

    $data = [];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $body)) {
            $value = $body[$field];

            if (in_array($field, ['name', 'sku', 'description', 'image_url', 'status', 'supplier'])) {
                $value = trim((string) $value);
                if ($value === '') {
                    $value = null;
                }
            }

            // Handle category_id separately — keep as-is if valid UUID, skip if invalid
            if ($field === 'category_id') {
                $value = trim((string) $value);
                if ($value === '') {
                    // No category selected — skip sending it entirely
                    continue;
                }
                if (!preg_match('/^[0-9a-fA-F\-]{36}$/', $value)) {
                    continue;
                }
            }

            if ($field === 'price') {
                if (!is_numeric($value)) {
                    continue;
                }
                $value = (float) $value;
            }

            if (in_array($field, ['stock_quantity', 'reorder_threshold'])) {
                if ($value === '' || !is_numeric($value)) {
                    continue;
                }
                $value = (int) $value;
            }

            $data[$field] = $value;
        }
    }

    if (empty($data)) {
        $response->getBody()->write(json_encode([
            'error' => 'Validation failed',
            'message' => 'At least one valid product field must be provided for update.'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    try {
        $updated = $auth->update('products', 'id=eq.' . $id, $data);
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Unable to update product', 'details' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    if (empty($updated)) {
        $response->getBody()->write(json_encode(['error' => 'Product not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode($updated));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// DELETE /api/products/{id} — Delete a product (admin only)
// ============================================================
$app->delete('/api/products/{id}', function (Request $request, Response $response, array $args) {

    $id = $args['id'];

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    try {
        $deleted = $auth->delete('products', 'id=eq.' . $id);
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Unable to delete product', 'details' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    if ($deleted === null || $deleted === false) {
        $response->getBody()->write(json_encode(['error' => 'Product not found or already deleted']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['message' => 'Product deleted', 'id' => $id]));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/products/upload-image — Upload a product image (authenticated)
// ============================================================
$app->post('/api/products/upload-image', function (Request $request, Response $response) {

    $files = $request->getUploadedFiles();
    $file = $files['image'] ?? null;

    if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode(['error' => 'No valid file uploaded']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file->getClientMediaType(), $allowedTypes)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid file type. Allowed: jpeg, png, webp, gif']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if ($file->getSize() > 5 * 1024 * 1024) {
        $response->getBody()->write(json_encode(['error' => 'File too large. Maximum size is 5MB']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $filename = uniqid() . '-' . $file->getClientFilename();

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $fileData = (string) $file->getStream();
    $auth->uploadFile('product-images', $filename, $fileData, $file->getClientMediaType());

    $publicUrl = $auth->getPublicUrl('product-images', $filename);

    $response->getBody()->write(json_encode(['image_url' => $publicUrl]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());