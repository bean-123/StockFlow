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

    // Current query — fetches everything, no filtering
    $products = $auth->query('products', [
        'select' => '*,categories(name)',
        'order' => 'name.asc'
    ]);

    // --- POST-PROCESSING (Exercise 1) ---
    // TODO: Transform $products before sending to the frontend
    // Example: $processed = array_map(function ($product) { ... }, $products);
    // Then return $processed instead of $products

    $response->getBody()->write(json_encode($products));
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
    // $auth = new SupabaseAuth();
    //
    // TODO: Query for a single product by ID
    // TODO: Return 404 if not found
    // TODO: Return the product as JSON

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 4: GET /api/products/{id} is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

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

    // --- PRE-PROCESSING ---
    // $body = $request->getParsedBody();
    //
    // TODO: Validate required fields (name, sku, price)
    // TODO: Return 400 with error message if validation fails
    //
    // TODO: Sanitize and prepare data
    // $data = [
    //     'name' => trim($body['name']),
    //     'sku' => trim($body['sku']),
    //     'price' => (float)$body['price'],
    //     'description' => trim($body['description'] ?? ''),
    //     'image_url' => $body['image_url'] ?? null,
    // ];
    //
    // --- QUERY SUPABASE ---
    // $auth = new SupabaseAuth();
    // $auth->setToken($request->getAttribute('token'));
    // $created = $auth->insert('products', $data);
    //
    // --- POST-PROCESSING ---
    // TODO: Return the created product with 201 status

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 4: POST /api/products is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

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

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 3).
$app->put('/api/products/{id}', function (Request $request, Response $response, array $args) {

    // $id = $args['id'];
    // $body = $request->getParsedBody();
    //
    // TODO: Build $data with only the fields that were sent
    // TODO: Validate — return 400 if $data is empty
    // TODO: Update via Supabase and return result

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 4: PUT /api/products/{id} is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

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

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 4).
$app->delete('/api/products/{id}', function (Request $request, Response $response, array $args) {

    // $id = $args['id'];
    //
    // TODO: Delete or archive the product
    // TODO: Return confirmation

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 4: DELETE /api/products/{id} is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

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

// STUB: Returns "not implemented" until students implement Exercise 5.
$app->post('/api/products/upload-image', function (Request $request, Response $response) {

    // $files = $request->getUploadedFiles();
    // $file = $files['image'] ?? null;
    //
    // --- PRE-PROCESSING ---
    // TODO: Check that a file was uploaded
    // if (!$file || $file->getError() !== UPLOAD_ERR_OK) { ... return 400 }
    //
    // TODO: Validate file type
    // $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    // if (!in_array($file->getClientMediaType(), $allowedTypes)) { ... return 400 }
    //
    // TODO: Validate file size (max 5MB)
    // if ($file->getSize() > 5 * 1024 * 1024) { ... return 400 }
    //
    // TODO: Generate unique filename
    // $filename = uniqid() . '-' . $file->getClientFilename();
    //
    // --- UPLOAD TO SUPABASE STORAGE ---
    // $auth = new SupabaseAuth();
    // $auth->setToken($request->getAttribute('token'));
    //
    // $fileData = (string) $file->getStream();
    // $auth->uploadFile('product-images', $filename, $fileData, $file->getClientMediaType());
    //
    // $publicUrl = $auth->getPublicUrl('product-images', $filename);
    //
    // --- POST-PROCESSING ---
    // TODO: Return the public URL
    // $response->getBody()->write(json_encode(['image_url' => $publicUrl]));
    // return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

    $response->getBody()->write(json_encode([
        'error' => 'Exercise 5: POST /api/products/upload-image is not implemented yet'
    ]));
    return $response->withStatus(501)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
