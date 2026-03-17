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
// Currently returns raw data from Supabase.
//
// EXERCISE 1: Add post-processing to transform the data:
//   - Format price as a string with 2 decimal places
//   - Add a 'stock_status' field: 'out_of_stock', 'low_stock', or 'in_stock'
//     (hint: compare stock_quantity to reorder_threshold)
//   - Add 'category_name' as a flat string instead of nested object
//   - Keep 'image_url' — the frontend uses it for thumbnails
//   - Remove fields the frontend doesn't need (supplier, reorder_threshold)
//
// EXERCISE 2: Add pre-processing for search and filtering:
//   - Read query params: ?search=wireless&category=Audio&status=active
//   - Build Supabase filters from those params
//   - Add sorting: ?sort=price&order=desc
//   - Add pagination: ?page=1&limit=10
//
// See _route_examples.php for how to read query params and build filters.
// ============================================================

$app->get('/api/products', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();

    // --- PRE-PROCESSING (Exercise 2) ---
    // TODO: Read query parameters from the request
    // $params = $request->getQueryParams();
    // $search = $params['search'] ?? null;
    // $category = $params['category'] ?? null;
    // ... then build $queryParams based on what was sent

    $params = $request->getQueryParams();
    $search = trim((string) ($params['search'] ?? ''));
    $category = trim((string) ($params['category'] ?? ''));
    $status = trim((string) ($params['status'] ?? ''));
    $sort = trim((string) ($params['sort'] ?? 'name'));
    $order = strtolower(trim((string) ($params['order'] ?? 'asc')));
    $page = max(1, (int) ($params['page'] ?? 1));
    $limit = max(1, min(100, (int) ($params['limit'] ?? 50)));

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
        'limit' => $limit,
        'offset' => ($page - 1) * $limit,
    ];

    if ($search !== '') {
        // Use PostgREST wildcard (*) instead of raw % to avoid URL-encoding
        // issues in the custom SupabaseAuth query builder.
        $queryParams['name'] = 'ilike.*' . $search . '*';
    }

    if ($status !== '') {
        $queryParams['status'] = 'eq.' . $status;
    }

    if ($category !== '') {
        // Match category by name from categoryMap and filter by category_id
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
            // No category match => no products
            $products = [];
            $processed = [];
            $response->getBody()->write(json_encode($processed));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    $products = $auth->query('products', $queryParams);

    // --- POST-PROCESSING (Exercise 1) ---
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

    $response->getBody()->write(json_encode($processed));
    return $response->withHeader('Content-Type', 'application/json');
});


// ============================================================
// GET /api/categories — List categories (public)
// ============================================================
$app->get('/api/categories', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();

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
        // Try to auto-seed defaults into DB for student exercise environments.
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

    if (empty($categories)) {
        // Provide UI categories even when DB is empty, blocked by RLS, or seeding fails.
        $categories = [
            ['id' => '00000000-0000-0000-0000-000000000001', 'name' => 'Audio'],
            ['id' => '00000000-0000-0000-0000-000000000002', 'name' => 'Cables & Adapters'],
            ['id' => '00000000-0000-0000-0000-000000000003', 'name' => 'Displays'],
            ['id' => '00000000-0000-0000-0000-000000000004', 'name' => 'Keyboards'],
            ['id' => '00000000-0000-0000-0000-000000000005', 'name' => 'Mice & Peripherals'],
            ['id' => '00000000-0000-0000-0000-000000000006', 'name' => 'Power & Charging'],
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
// EXERCISE 4 (Step 1): Students build this route
// This is needed before update/delete — you need to fetch one product.
//
// Hints:
//   - $args['id'] contains the UUID from the URL
//   - Use $auth->query('products', ['id' => 'eq.' . $id, 'select' => '...'])
//   - Supabase returns an array even for single items — use [0] to get the first
//   - Return 404 if the product doesn't exist
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 1).
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
// EXERCISE 4 (Step 2): Students build this route
//
// Hints:
//   - Use $request->getParsedBody() to get the JSON body
//   - Validate required fields: name, sku, price
//   - Sanitize: trim strings, cast price to float
//   - Include image_url if it was sent (from Exercise 5)
//   - Use $auth->insert('products', $data)
//   - Return 201 status on success
//   - Don't forget ->add(new AuthMiddleware()) at the end!
//
// The frontend sends:
//   { name: "...", sku: "...", price: 29.99, description: "...", category_id: "uuid", image_url: "..." }
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 2).
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


    // Validate category_id against DB categories. If no matching DB record, attempt to create.
    $defaultCategoryMap = [
        '00000000-0000-0000-0000-000000000001' => 'Audio',
        '00000000-0000-0000-0000-000000000002' => 'Cables & Adapters',
        '00000000-0000-0000-0000-000000000003' => 'Displays',
        '00000000-0000-0000-0000-000000000004' => 'Keyboards',
        '00000000-0000-0000-0000-000000000005' => 'Mice & Peripherals',
        '00000000-0000-0000-0000-000000000006' => 'Power & Charging',
    ];

    if (isset($data['category_id']) && $data['category_id'] !== null) {
        $categoryId = $data['category_id'];

        if (!preg_match('/^[0-9a-fA-F\-]{36}$/', $categoryId)) {
            unset($data['category_id']);
        } else {
            $existing = [];
            try {
                $existing = $auth->query('categories', ['select' => 'id,name', 'id' => 'eq.' . $categoryId]);
            } catch (Exception $e) {
                // reading categories may fail due RLS; we'll attempt to ensure mapping from fallback
                $existing = [];
            }

            if (empty($existing)) {
                // If category exists in our fallback mapping, ensure a real row exists with that name.
                if (isset($defaultCategoryMap[$categoryId])) {
                    $fallbackName = $defaultCategoryMap[$categoryId];
                    try {
                        $auth->insert('categories', ['name' => $fallbackName]);
                    } catch (Exception $e) {
                        // ignore duplicates
                    }

                    try {
                        $existing = $auth->query('categories', ['select' => 'id,name', 'name' => 'eq.' . $fallbackName]);
                    } catch (Exception $e) {
                        $existing = [];
                    }

                    if (!empty($existing)) {
                        $data['category_id'] = $existing[0]['id'] ?? $categoryId;
                        $categoryId = $data['category_id'];
                    }
                }
            }

            if (empty($existing)) {
                unset($data['category_id']);
            }
        }
    }

    // Remove null fields to avoid accidental FK constraint issues
    if (isset($data['category_id']) && $data['category_id'] === null) {
        unset($data['category_id']);
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

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
// EXERCISE 4 (Step 3): Students build this route
//
// Hints:
//   - Only update fields that were actually sent in the body
//   - Include image_url if a new image was uploaded (Exercise 5)
//   - Use $auth->update('products', 'id=eq.' . $id, $data)
//   - Return 400 if no fields to update
// ============================================================

// PUT /api/products/{id} — Update a product (admin/manager only)
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

            if (in_array($field, ['name', 'sku', 'description', 'category_id', 'image_url', 'status', 'supplier'])) {
                $value = trim((string) $value);
                if ($value === '') {
                    $value = null;
                }
            }

            if ($field === 'category_id' && $value !== null) {
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
// EXERCISE 4 (Step 4): Students build this route
//
// Hints:
//   - Use $auth->delete('products', 'id=eq.' . $id)
//   - Consider: should you hard-delete or soft-delete (set status='archived')?
//   - If soft-delete, use update() instead of delete()
//   - Return a confirmation message
// ============================================================

// DELETE /api/products/{id} — Delete a product (admin only)
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
// EXERCISE 5: Students build this route
//
// This route receives a file upload, sends it to Supabase Storage,
// and returns the public URL. The frontend then uses this URL when
// creating or updating a product.
//
// How file uploads work in Slim:
//   - The frontend sends a FormData object (not JSON)
//   - Slim parses it automatically via addBodyParsingMiddleware()
//   - Use $request->getUploadedFiles() to get the file
//   - Each file is a PSR-7 UploadedFile object with methods:
//     ->getError()          — check for upload errors (UPLOAD_ERR_OK = success)
//     ->getClientFilename() — original filename (e.g., "photo.jpg")
//     ->getSize()           — file size in bytes
//     ->getClientMediaType()— MIME type (e.g., "image/jpeg")
//     ->getStream()         — the file data as a stream
//
// Steps:
//   1. Get the uploaded file from the request
//   2. Validate: file exists, no upload errors, correct type (image/*), size limit
//   3. Generate a unique filename (to avoid collisions)
//   4. Upload to Supabase Storage using $auth->uploadFile()
//   5. Get the public URL using $auth->getPublicUrl()
//   6. Return the URL as JSON
//
// Hints:
//   - Generate unique filename: $filename = uniqid() . '-' . $file->getClientFilename();
//   - Read file data: $fileData = (string) $file->getStream();
//   - Allowed types: ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
//   - Max size: 5 * 1024 * 1024 (5MB)
//   - Bucket name: 'product-images' (must be created in Supabase first — see TASKS.md)
// ============================================================

$app->post('/api/products/upload-image', function (Request $request, Response $response) {

    $files = $request->getUploadedFiles();
    $file = $files['image'] ?? null;

    if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode([
            'error' => 'No file uploaded or upload failed',
            'code' => $file ? $file->getError() : null,
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mimeType = $file->getClientMediaType();

    if (!in_array($mimeType, $allowedTypes, true)) {
        $response->getBody()->write(json_encode([
            'error' => 'Invalid file type',
            'allowed_types' => $allowedTypes,
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $maxSize = 5 * 1024 * 1024;
    if ($file->getSize() > $maxSize) {
        $response->getBody()->write(json_encode([
            'error' => 'File too large',
            'max_size_bytes' => $maxSize,
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $clientFilename = $file->getClientFilename() ?: 'upload';
    $safeFilename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $clientFilename);
    $filename = uniqid('', true) . '-' . $safeFilename;

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    try {
        $fileData = (string) $file->getStream();
        $auth->uploadFile('product-images', $filename, $fileData, $mimeType);
        $publicUrl = $auth->getPublicUrl('product-images', $filename);
    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => 'Failed to upload image',
            'details' => $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['image_url' => $publicUrl]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
