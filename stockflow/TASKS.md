# Lesson 6 — Backend Exercises: StockFlow API

## Setup

### 1. Start the PHP backend
```bash
cd stockflow/api
composer install           # Install dependencies (first time only)
php -S localhost:8005 -t public/
```

### 2. Start the React frontend
```bash
cd stockflow/client/@
npm install                # Install dependencies (first time only)
npm run dev                # Starts on http://localhost:5174
```

### 3. Check Supabase RLS policies

The products table needs a **public read** policy so the product list works without login. In the Supabase Dashboard, go to **Authentication > Policies > products** and check that the SELECT policy uses `USING (true)`:

```sql
-- Products should be readable by everyone (no login required)
ALTER POLICY "Anyone can view products" ON products USING (true);
```

> **Why?** The `GET /api/products` route has no AuthMiddleware (it's public). If the RLS policy requires `auth.uid() IS NOT NULL`, Supabase will return an empty array for unauthenticated requests.

### 4. Set up Supabase Storage (for Exercise 5)

Run this SQL in the **Supabase SQL Editor** to create the storage bucket for product images:

```sql
-- Create a public bucket for product images
INSERT INTO storage.buckets (id, name, public)
VALUES ('product-images', 'product-images', true);

-- Allow anyone (authenticated) to view images
CREATE POLICY "Public read access for product images"
ON storage.objects FOR SELECT
USING (bucket_id = 'product-images');

-- Allow authenticated users to upload images
CREATE POLICY "Authenticated users can upload product images"
ON storage.objects FOR INSERT
WITH CHECK (bucket_id = 'product-images' AND auth.uid() IS NOT NULL);

-- Allow authenticated users to delete their uploads
CREATE POLICY "Authenticated users can delete product images"
ON storage.objects FOR DELETE
USING (bucket_id = 'product-images' AND auth.uid() IS NOT NULL);
```

> **Note:** The `image_url` column already exists in the `products` table — no ALTER TABLE needed.

### 5. Check it works
- Open http://localhost:5174 — you should see the Products tab
- Products should load from the API (names, SKUs, prices)
- The "Status" column will show "—" until you complete Exercise 1
- Sign in with Google to access the authenticated tabs

---

## How This Works

The frontend is **already built**. Your job is to write the **PHP backend** code.

Each exercise tells you:
- **Which PHP file** to edit
- **What the frontend expects** (the JSON structure)
- **Hints** for how to implement it

### Key files
| File | Purpose |
|------|---------|
| `api/src/Routes/_route_examples.php` | **Read this first!** Full examples of GET, POST, PUT, DELETE routes |
| `api/src/Routes/products.php` | Exercises 1, 2, 4, 5 |
| `api/src/Routes/orders.php` | Exercises 3, 6 |
| `api/src/Routes/stock.php` | Exercise 3 |
| `api/src/Routes/dashboard.php` | Exercise 7 |
| `api/src/Routes/ai.php` | Exercise 8 |
| `api/src/Auth/SupabaseAuth.php` | Database + storage access (query, insert, update, delete, uploadFile) |
| `api/src/AI/GeminiAI.php` | Gemini API wrapper (already built) |
| `api/public/index.php` | App entry point — middleware setup, CORS, route loading (do not edit) |

### SupabaseAuth Quick Reference
```php
$auth = new SupabaseAuth();
$auth->setToken($request->getAttribute('token'));  // For protected routes

// READ
$rows = $auth->query('products', ['select' => '*', 'order' => 'name.asc']);

// CREATE
$created = $auth->insert('products', ['name' => 'Widget', 'price' => 9.99]);

// UPDATE
$updated = $auth->update('products', 'id=eq.' . $id, ['price' => 19.99]);

// DELETE
$auth->delete('products', 'id=eq.' . $id);

// UPLOAD FILE (Exercise 5)
$auth->uploadFile('product-images', $filename, $fileData, $mimeType);
$url = $auth->getPublicUrl('product-images', $filename);
```

---

## Exercise 1: Pre-process Product Data

**Goal:** Transform raw database data before sending it to the frontend.

**File:** `api/src/Routes/products.php` — the `GET /api/products` route

**What to do:**
After fetching products from Supabase, loop through them and add:

1. **`category_name`** — Extract from the nested `categories.name` into a flat string
2. **`stock_status`** — Calculate based on stock levels:
   - `"out_of_stock"` if `stock_quantity` is 0
   - `"low_stock"` if `stock_quantity` <= `reorder_threshold`
   - `"in_stock"` otherwise
3. **`price`** — Format as string with 2 decimal places (e.g., `"129.00"`)
4. **Keep `image_url`** — The frontend uses it to show product thumbnails
5. Remove fields the frontend doesn't need: `supplier`, `reorder_threshold`

**PHP hint:**
```php
$processed = array_map(function ($product) {
    return [
        'id' => $product['id'],
        'name' => $product['name'],
        'image_url' => $product['image_url'],
        // ... add your fields here
    ];
}, $products);
```

**Test it:** Check the Products tab — the Status column should show colored labels.

---

## Exercise 2: Search and Filtering

**Goal:** Read query parameters from the URL and use them to filter data.

**File:** `api/src/Routes/products.php` — the `GET /api/products` route

**What to do:**
The frontend already sends query params when you type in the search box or change the dropdowns. You need to read them in PHP and pass them to Supabase.

1. Read query params: `$params = $request->getQueryParams();`
2. If `search` is set, add a filter: `'name' => 'ilike.%' . $search . '%'`
3. If `category` is set, filter by category name (you'll need to think about how — categories are a joined table)
4. If `status` is set, add: `'status' => 'eq.' . $status`

**Stretch:** Add pagination with `page` and `limit` params.

**Test it:** Type in the search box — the product list should filter. Change the category dropdown — only matching products should show.

---

## Exercise 3: Date and Time Handling

**Goal:** Format timestamps and calculate relative dates on the backend.

**File:** `api/src/Routes/orders.php` — the `GET /api/orders` route
**File:** `api/src/Routes/stock.php` — build both routes

### Step 1: Format order dates
In the orders route, add post-processing:
1. **`created_date`** — Human-readable format: `"9 Mar 2026, 14:30"`
2. **`created_ago`** — Relative time: `"2 days ago"`, `"Today"`, `"Yesterday"`
3. **`total_amount`** — Format with 2 decimal places

**PHP date/time hints:**
```php
$timestamp = strtotime($row['created_at']);
$formatted = date('j M Y, H:i', $timestamp);
$daysAgo = floor((time() - $timestamp) / 86400);

if ($daysAgo === 0) $relative = 'Today';
elseif ($daysAgo === 1) $relative = 'Yesterday';
else $relative = $daysAgo . ' days ago';
```

### Step 2: Build GET /api/stock/movements
Replace the stub route in `stock.php` and implement it:
- Query the `stock_movements` table
- Join with products: `'select' => '*,products(name,sku)'`
- Format dates the same way as orders
- Flatten `products.name` into `product_name`

### Step 3: Build POST /api/stock/movements
Replace the stub and implement:
- Validate: `product_id`, `quantity` (> 0), `movement_type` (in/out/adjustment)
- Insert into `stock_movements`
- Fetch the product's current `stock_quantity`
- Calculate new quantity (add for "in", subtract for "out")
- Update the product with the new `stock_quantity`

**Test it:** Check the Orders tab — dates should be formatted. Go to the Stock tab — record a movement and see it appear in the list.

---

## Exercise 4: CRUD — Products

**Goal:** Build create, read (single), update, and delete endpoints.

**File:** `api/src/Routes/products.php` — replace each stub route with your implementation

### Step 1: GET /api/products/{id}
- Fetch a single product by UUID
- Return 404 if not found
- Include category info

### Step 2: POST /api/products
- Validate required fields: `name`, `sku`, `price`
- Sanitize: `trim()` strings, cast `price` to `(float)`
- Include `image_url` if it was sent (from Exercise 5)
- Insert via `$auth->insert()`
- Return 201 status

### Step 3: PUT /api/products/{id}
- Only update fields that were sent
- Include `image_url` if a new image was uploaded
- Validate that at least one field was provided
- Use `$auth->update()`

### Step 4: DELETE /api/products/{id}
- Choose: hard delete with `$auth->delete()` or soft delete (set status to 'archived')
- Return confirmation message

**Test it:** Go to the "+ Product" tab, fill in the form, and submit. Check that it appears in the Products list.

---

## Exercise 5: Image Upload

**Goal:** Upload product images to Supabase Storage and link them to products.

**File:** `api/src/Routes/products.php` — the `POST /api/products/upload-image` route

**Prerequisites:**
- Make sure you ran the Supabase Storage SQL from the Setup section above
- Exercise 4 should be completed first (so products can be created/updated with image_url)

### How it works
The frontend uses a two-step process:
1. Upload the image file → backend saves it to Supabase Storage → returns a public URL
2. Create/update the product with the `image_url` field set to that URL

### What to do
Replace the stub route for `POST /api/products/upload-image`:

1. **Get the uploaded file:**
   ```php
   $files = $request->getUploadedFiles();
   $file = $files['image'] ?? null;
   ```

2. **Validate the upload:**
   ```php
   // Check file exists and uploaded OK
   if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
       // return 400 error
   }

   // Check file type (only images)
   $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
   if (!in_array($file->getClientMediaType(), $allowedTypes)) {
       // return 400 error
   }

   // Check file size (max 5MB)
   if ($file->getSize() > 5 * 1024 * 1024) {
       // return 400 error
   }
   ```

3. **Generate a unique filename:**
   ```php
   // uniqid() gives a unique prefix so files don't overwrite each other
   $filename = uniqid() . '-' . $file->getClientFilename();
   ```

4. **Upload to Supabase Storage:**
   ```php
   $auth = new SupabaseAuth();
   $auth->setToken($request->getAttribute('token'));

   $fileData = (string) $file->getStream();
   $auth->uploadFile('product-images', $filename, $fileData, $file->getClientMediaType());
   ```

5. **Return the public URL:**
   ```php
   $publicUrl = $auth->getPublicUrl('product-images', $filename);

   $response->getBody()->write(json_encode(['image_url' => $publicUrl]));
   return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
   ```

### Then update your product CRUD routes
In the `POST /api/products` and `PUT /api/products/{id}` routes (Exercise 4), make sure to include `image_url` in the data you send to Supabase:
```php
$data = [
    'name' => trim($body['name']),
    'sku' => trim($body['sku']),
    'price' => (float)$body['price'],
    // ... other fields
    'image_url' => $body['image_url'] ?? null,  // From the upload step
];
```

**Test it:** Go to the "+ Product" tab. Select an image file, fill in the form, and submit. The image should appear as a thumbnail in the Products list.

---

## Exercise 6: CRUD — Orders

**Goal:** Build order creation and status management.

**File:** `api/src/Routes/orders.php` — replace each stub route with your implementation

### Step 1: Filter orders by status
Add query param support to the existing `GET /api/orders` route.

### Step 2: GET /api/orders/{id}
- Fetch the order AND its order_items (two queries, combined in PHP)
- Return as: `{ ...order, items: [...] }`

### Step 3: POST /api/orders (hardest exercise)
This creates an order with its items. Three database operations:
1. Insert the order (with `total_amount: 0`)
2. Loop items: calculate `line_total`, insert each into `order_items`
3. Update the order's `total_amount` with the sum of line totals

**Important:** Calculate the total on the backend, not the frontend! Never trust client-side math for financial data.

### Step 4: PUT /api/orders/{id}/status
Implement a state machine — only allow valid transitions:
```
draft → confirmed
draft → cancelled
confirmed → fulfilled
confirmed → cancelled
```
Return 400 with an error message for invalid transitions (e.g., "Cannot change from fulfilled to draft").

**Test it:** Go to "+ Order" tab, create an order. Then on the Orders tab, try the Confirm/Fulfill/Cancel buttons.

---

## Exercise 7: Dashboard Analytics

**Goal:** Aggregate data from multiple tables into a summary.

**File:** `api/src/Routes/dashboard.php` — replace the stub with your implementation

This combines everything you've learned: fetching data, filtering, calculating, and formatting.

**What to return:**
```json
{
  "inventory": {
    "total_products": 18,
    "total_value": 12450.00,
    "low_stock_count": 4,
    "out_of_stock_count": 2
  },
  "orders": {
    "total_orders": 5,
    "by_status": { "draft": 1, "confirmed": 1, "fulfilled": 2, "cancelled": 1 },
    "total_revenue": 2602.00
  },
  "low_stock_products": [
    { "name": "USB-C Hub Pro", "stock_quantity": 2, "reorder_threshold": 10 }
  ]
}
```

**PHP hints:**
```php
// Count items matching a condition
$outOfStock = count(array_filter($products, fn($p) => $p['stock_quantity'] == 0));

// Sum a calculated field
$totalValue = array_sum(array_map(
    fn($p) => (float)$p['price'] * (int)$p['stock_quantity'],
    $products
));

// Sort array by a field
usort($lowStock, fn($a, $b) => $a['stock_quantity'] - $b['stock_quantity']);

// Take first 5
$top5 = array_slice($lowStock, 0, 5);
```

**Test it:** Go to the Dashboard tab — you should see summary cards and a low stock alert table.

---

## Exercise 8: AI Integration

**Goal:** Use the Gemini AI class to add smart features.

**File:** `api/src/Routes/ai.php` — replace each stub route with your implementation

The `GeminiAI` class is already built at `api/src/AI/GeminiAI.php`. You just need to use it.

### Step 1: POST /api/ai/describe
- Fetch the product by ID from Supabase
- Build a prompt: "Write a 2-3 sentence product description for: {name}. Category: {category}. Price: {price} EUR."
- Send to Gemini: `$ai = new GeminiAI(); $result = $ai->ask($prompt);`
- Return: `{ "description": "..." }`
- Wrap in try/catch for error handling

### Step 2: POST /api/ai/stock-advice
- Fetch all products
- Filter in PHP: keep only products where `stock_quantity <= reorder_threshold`
- Build a prompt listing the low-stock items
- Ask Gemini for reorder recommendations
- Return: `{ "advice": "...", "products": [...] }`

### Step 3 (Stretch): POST /api/ai/summarize-orders
- Fetch orders, build a summary prompt, return AI analysis

**Test it:** Go to the AI tab, select a product and click Generate. Try the Stock Advice button.

---

## Exercise Order (Recommended)

| Order | Exercise | Difficulty | Builds on |
|-------|----------|------------|-----------|
| 1st | Exercise 1 — Pre-processing | Easy | — |
| 2nd | Exercise 2 — Search & Filter | Easy-Medium | Exercise 1 |
| 3rd | Exercise 3 — Dates (orders) | Medium | Exercise 1 |
| 4th | Exercise 4 — CRUD Products | Medium | Exercises 1-2 |
| 5th | Exercise 5 — Image Upload | Medium | Exercise 4 |
| 6th | Exercise 3 — Dates (stock) | Medium | Exercises 3-4 |
| 7th | Exercise 6 — CRUD Orders | Hard | Exercises 3-4 |
| 8th | Exercise 7 — Dashboard | Medium-Hard | Exercises 1-6 |
| 9th | Exercise 8 — AI Integration | Medium | Exercise 4 |

---

## Common Issues

**"No token provided" error** — The route needs `->add(new AuthMiddleware())` AND the frontend needs a token in localStorage. Log in first via the Auth flow.

**Products load but filters don't work** — You haven't implemented Exercise 2 yet. The backend ignores query params until you add the code.

**"Status" column shows "—"** — You haven't implemented Exercise 1 yet. The frontend is looking for `stock_status` which doesn't exist in the raw data.

**Products list is empty (no errors)** — The Supabase RLS policy for products must allow public reads. See Setup step 3.

**Image upload returns error** — Make sure you created the Supabase Storage bucket (see Setup step 4). Also check that the `product-images` bucket is set to public.

**CORS errors** — Make sure `CLIENT_URL` in `.env` matches your React dev server URL (default: `http://localhost:5174`).

**Supabase returns 403** — The user doesn't have the right role for that operation. Check RLS policies.
