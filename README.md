# StockFlow (PHP + React)

StockFlow is a sample inventory and order management system built as an educational exercise. It has a PHP backend (Slim + Supabase) and a React frontend (Vite).

## 🧩 What it does

- Product CRUD (create, read, update, delete)
- Order management with line items
- Stock movements and inventory processing
- Dashboard with stats, low-stock and out-of-stock list
- AI-powered routes using Google Gemini (describe, stock advice, summarize orders)
- Authentication via Supabase JWT

## 🛠️ Tech stack

- Backend: PHP 8.x, Slim framework
- Auth + DB: Supabase (REST v1 API via custom `SupabaseAuth` class)
- AI: Google Gemini via `StockFlow\AI\GeminiAI`
- Frontend: React, Vite
- Containers: Docker / docker-compose

## 📁 Repository layout

- `api/` - backend
  - `public/` - web root
    - `index.php` - app bootstrap
  - `src/Routes/` - route handlers
    - `products.php`, `orders.php`, `dashboard.php`, `ai.php`, etc.
  - `src/Auth/SupabaseAuth.php` - REST wrapper for Supabase
  - `src/AI/GeminiAI.php` - Gemini request helper
  - `src/Middleware/AuthMiddleware.php`
- `client/@/` - React frontend
  - `src/components/` - UI screens
  - `src/services/api.js` - REST API client
- `docker-compose.yml` - service orchestration
- `api/.env` - example env variables (not tracked with secrets)

## ▶️ Setup (local)

1. Clone repo

   ```bash
   git clone <repo>
   cd php-lessons/stockflow
   ```

2. Install dependencies

- Backend

  ```bash
  cd api
  composer install
  ```

- Frontend
  ```bash
  cd client/@
  npm install
  ```

3. Configure `.env` in `api/`

Copy template and add values:

```ini
SUPABASE_URL=https://<your-project>.supabase.co
SUPABASE_ANON_KEY=sb_...
SITE_URL=http://localhost:8005
CLIENT_URL=http://localhost:5174
GEMINI_API_KEY=<your_gemini_key>
```

4. Start the services

- Option A (docker-compose)

  ```bash
  docker-compose up --build
  ```

- Option B (manual)
  1. Backend (from `api/`):
     ```bash
     php -S localhost:8005 -t public
     ```
  2. Frontend (from `client/@/`):
     ```bash
     npm run dev
     ```

## 🚪 Open app

- Frontend: `http://localhost:5174`
- Backend API health: `http://localhost:8005/api/products` (authenticated endpoints need Bearer token)

## 🔐 Authentication

- Use Google login on app (`/auth/login-url`) to get JWT
- `client/@/src/services/api.js` forwards `Authorization: Bearer <token>` for protected routes
- Middleware: `api/src/Middleware/AuthMiddleware.php`

## 🗂️ Main backend routes

### Products

- `GET /api/products`
- `GET /api/products/{id}`
- `POST /api/products`
- `PUT /api/products/{id}`
- `DELETE /api/products/{id}`
- `POST /api/products/upload-image`

### Orders

- `GET /api/orders`
- `GET /api/orders/{id}`
- `POST /api/orders`
- `PUT /api/orders/{id}/status`

### Stock

- `GET /api/stock/movements`
- `POST /api/stock/movements`

### Dashboard

- `GET /api/dashboard/summary` (inventory stats, low and out-of-stock products)

### AI

- `POST /api/ai/describe` (product marketing description)
- `POST /api/ai/stock-advice` (reorder advice for low stock)
- `POST /api/ai/summarize-orders` (7-day order trend summary)

## 🧠 AI features behavior

- `GeminiAI::ask()` uses `$_ENV['GEMINI_API_KEY']` to call Google Gemini REST
- On quota errors, fallback text is returned (so UI remains usable even when quota is 0)

## 🧪 Testing data (manual)

- Use `api/public/test.php?token=<jwt>` to validate Supabase queries.
- Inspect values and confirm status.

## 🐞 Common issues

- `Supabase error (401): Expected 3 parts in JWT` means auth header is missing/malformed.
- `Gemini API error: quota exceeded` means no AI quota on key; fallback text is used.
- CORS: verify `Access-Control-Allow-Origin` is set to `http://localhost:5174` in backend.

## 🧹 Cleanup

```bash
docker-compose down
```

## 🍰 Tip

If running from development branches, restart the backend after route changes to pick up new code.
