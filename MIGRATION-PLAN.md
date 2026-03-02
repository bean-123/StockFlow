# Migration Plan: PHP Backend + React Frontend

## Current State Summary

Your app is a **server-rendered PHP application** where PHP handles both logic and HTML output. It uses:

- **Supabase** as the database (queried via PHP cURL / PostgREST)
- **Supabase Auth** for Google OAuth
- **Google Gemini** for AI features
- **Docker** (PHP-Apache + MySQL + phpMyAdmin) for local dev
- **File-based routing** (each page is a `.php` file)
- **Bootstrap 3** for styling

The app has real functionality: authentication, products listing, orders display, notes CRUD, and AI story generation.

---

## The Architecture: PHP API Backend + React SPA Frontend

```
┌──────────────────────────────────────────────────────────────┐
│                        BROWSER                               │
│  ┌────────────────────────────────────────────────────────┐  │
│  │              React SPA (Vite + TypeScript)              │  │
│  │  - React Router for navigation                         │  │
│  │  - Fetch/Axios calls to PHP API                        │  │
│  │  - Tailwind CSS or similar for styling                 │  │
│  └──────────────────────┬─────────────────────────────────┘  │
└─────────────────────────┼────────────────────────────────────┘
                          │ HTTP (JSON)
                          ▼
┌──────────────────────────────────────────────────────────────┐
│                    PHP REST API                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │  Slim Framework (lightweight PHP router)                │  │
│  │  - /api/auth/*      → Auth endpoints                   │  │
│  │  - /api/products    → Products CRUD                    │  │
│  │  - /api/orders      → Orders CRUD                      │  │
│  │  - /api/ai/generate → AI story generation              │  │
│  │  - /api/notes       → Notes CRUD                       │  │
│  └──────────────────────┬─────────────────────────────────┘  │
│                         │ cURL (as you already do)           │
└─────────────────────────┼────────────────────────────────────┘
                          │
                          ▼
┌──────────────────────────────────────────────────────────────┐
│                     SUPABASE (cloud)                         │
│  - PostgreSQL database                                       │
│  - Auth (Google OAuth)                                       │
│  - Row Level Security                                        │
└──────────────────────────────────────────────────────────────┘
```

---

## Step-by-Step Migration Plan

### Phase 1: Restructure the Repository

**Goal:** Separate PHP and React into distinct directories with independent tooling.

```
project-root/
├── api/                        # PHP backend
│   ├── public/
│   │   └── index.php           # Single entry point (front controller)
│   ├── src/
│   │   ├── Auth/
│   │   │   └── SupabaseAuth.php    # Your existing class (cleaned up)
│   │   ├── AI/
│   │   │   └── GeminiAI.php        # Your existing class
│   │   ├── Middleware/
│   │   │   └── AuthMiddleware.php   # Checks JWT on protected routes
│   │   └── Routes/
│   │       ├── auth.php
│   │       ├── products.php
│   │       ├── orders.php
│   │       ├── notes.php
│   │       └── ai.php
│   ├── .env                    # API keys (never committed)
│   ├── composer.json           # Dependencies (slim/slim, vlucas/phpdotenv)
│   └── Dockerfile
├── client/                     # React frontend
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── hooks/
│   │   ├── services/           # API client functions
│   │   ├── App.tsx
│   │   └── main.tsx
│   ├── package.json
│   ├── vite.config.ts
│   └── Dockerfile              # (for Render deployment)
├── docker-compose.yml          # Local dev: both services
└── README.md
```

**Tasks:**
1. Create `api/` and `client/` directories
2. Move and refactor PHP code into `api/`
3. Scaffold React app in `client/` with Vite

---

### Phase 2: Build the PHP REST API

**Goal:** Convert your existing PHP pages into JSON API endpoints.

#### 2a. Set up Slim Framework

```bash
cd api
composer init
composer require slim/slim slim/psr7 vlucas/phpdotenv
```

Slim is deliberately minimal — it just routes HTTP requests to your functions. This means your existing SupabaseAuth logic stays almost identical.

#### 2b. Create the API entry point

`api/public/index.php` becomes a single front controller:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// CORS middleware (so React can call the API)
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $_ENV['CLIENT_URL'] ?? '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

// Load route files
require __DIR__ . '/../src/Routes/auth.php';
require __DIR__ . '/../src/Routes/products.php';
require __DIR__ . '/../src/Routes/orders.php';
require __DIR__ . '/../src/Routes/notes.php';
require __DIR__ . '/../src/Routes/ai.php';

$app->run();
```

#### 2c. Convert each page to an API route

**Example: Orders (from your current 13-orders.php)**

```php
// src/Routes/orders.php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/orders', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();

    // Get token from Authorization header (sent by React)
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader) {
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $token = str_replace('Bearer ', '', $authHeader);
    $auth->setToken($token);  // New method to set token from header

    $orders = $auth->query('orders', ['order' => 'created_at.desc']);

    $response->getBody()->write(json_encode($orders));
    return $response->withHeader('Content-Type', 'application/json');
});
```

#### 2d. Endpoints to build

| Current Page               | API Endpoint          | Method | Auth Required |
| -------------------------- | --------------------- | ------ | ------------- |
| 11-authentication.php      | `/api/auth/login-url` | GET    | No            |
| callback.php               | `/api/auth/callback`  | POST   | No            |
| 11-authentication.php      | `/api/auth/user`      | GET    | Yes           |
| 11-authentication.php      | `/api/auth/logout`    | POST   | Yes           |
| 12-products.php            | `/api/products`       | GET    | Yes           |
| 13-orders.php              | `/api/orders`         | GET    | Yes           |
| 11-authentication.php      | `/api/notes`          | GET    | Yes           |
| 11-authentication.php      | `/api/notes`          | POST   | Yes           |
| 11-authentication.php      | `/api/notes/{id}`     | DELETE | Yes           |
| ai-integration.php         | `/api/ai/generate`    | POST   | No            |

---

### Phase 3: Build the React Frontend

**Goal:** Replace the PHP-rendered HTML with a React SPA.

#### 3a. Scaffold with Vite

```bash
cd client
npm create vite@latest . -- --template react-ts
npm install react-router-dom axios
```

#### 3b. Create an API service layer

```typescript
// src/services/api.ts
const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost:8005/api';

async function fetchApi(endpoint: string, options: RequestInit = {}) {
  const token = localStorage.getItem('supabase_token');
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
  const res = await fetch(`${API_BASE}${endpoint}`, { ...options, headers });
  if (!res.ok) throw new Error(`API error: ${res.status}`);
  return res.json();
}

export const api = {
  getOrders: () => fetchApi('/orders'),
  getProducts: () => fetchApi('/products'),
  getNotes: () => fetchApi('/notes'),
  addNote: (data: { title: string; content: string }) =>
    fetchApi('/notes', { method: 'POST', body: JSON.stringify(data) }),
  deleteNote: (id: string) =>
    fetchApi(`/notes/${id}`, { method: 'DELETE' }),
  generateStory: (genre: string) =>
    fetchApi('/ai/generate', { method: 'POST', body: JSON.stringify({ genre }) }),
  getLoginUrl: () => fetchApi('/auth/login-url'),
};
```

#### 3c. Pages to build (mapping from current PHP)

| React Page          | Replaces PHP File           | Features                     |
| ------------------- | --------------------------- | ---------------------------- |
| `pages/Home.tsx`    | home.php                    | Welcome page                 |
| `pages/Login.tsx`   | 11-authentication.php       | Google sign-in button        |
| `pages/Callback.tsx`| auth/callback.php           | Handle OAuth redirect        |
| `pages/Products.tsx`| 12-products.php             | Products table               |
| `pages/Orders.tsx`  | 13-orders.php               | Orders table                 |
| `pages/Notes.tsx`   | 11-authentication.php       | Notes CRUD                   |
| `pages/AIStory.tsx` | ai-integration.php          | Genre select + story display |

#### 3d. Auth flow in React

1. User clicks "Sign in with Google"
2. React calls `GET /api/auth/login-url` → gets Supabase OAuth URL
3. Browser redirects to Google → Supabase → your callback URL
4. `Callback.tsx` extracts tokens from URL fragment
5. Sends tokens to `POST /api/auth/callback` (or stores them directly)
6. Token stored in `localStorage`, sent with every API request

---

### Phase 4: Local Development Setup

**Goal:** Run both services locally with Docker Compose.

```yaml
# docker-compose.yml
version: '3.8'
services:
  api:
    build:
      context: ./api
      dockerfile: Dockerfile
    ports:
      - "8005:80"
    volumes:
      - ./api:/var/www/html
    environment:
      - CLIENT_URL=http://localhost:5173

  client:
    image: node:20-alpine
    working_dir: /app
    command: npm run dev -- --host
    ports:
      - "5173:5173"
    volumes:
      - ./client:/app
    environment:
      - VITE_API_URL=http://localhost:8005/api
```

**Updated PHP Dockerfile:**

```dockerfile
FROM php:8.2-apache
RUN apt-get update && apt-get install -y unzip curl
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN a2enmod rewrite
RUN docker-php-ext-install pdo pdo_mysql
COPY . /var/www/html/
RUN composer install --no-dev
```

Note: The MySQL + phpMyAdmin services are removed since you use Supabase as your primary database. Add them back if you still want local MySQL for learning.

---

### Phase 5: Deployment

#### Option A: Render (Recommended for this stack)

**Backend (PHP API) → Render Web Service (Docker)**

1. Create a `render.yaml` (Infrastructure as Code):
   ```yaml
   services:
     - type: web
       name: php-api
       runtime: docker
       dockerfilePath: ./api/Dockerfile
       envVars:
         - key: SUPABASE_URL
           sync: false
         - key: SUPABASE_ANON_KEY
           sync: false
         - key: GEMINI_API_KEY
           sync: false
         - key: CLIENT_URL
           sync: false
   ```
2. Render detects the Dockerfile, builds, and deploys
3. Set environment variables in the Render dashboard
4. Your API is live at `https://php-api-xxxx.onrender.com`

**Frontend (React) → Render Static Site**

1. Add to `render.yaml`:
   ```yaml
     - type: web
       name: client
       runtime: static
       buildCommand: cd client && npm install && npm run build
       staticPublishPath: ./client/dist
       envVars:
         - key: VITE_API_URL
           value: https://php-api-xxxx.onrender.com/api
   ```
2. Render builds the React app and serves the static files
3. React SPA is live at `https://client-xxxx.onrender.com`

**Cost:** Render free tier gives you a web service + static site. The free web service sleeps after 15 minutes of inactivity (cold starts of ~30s).

#### Option B: Vercel (Frontend) + Render (Backend)

- **React on Vercel**: Ideal — Vercel is built for frontend frameworks. Zero config for Vite.
- **PHP on Render**: Docker-based web service as above.
- This splits hosting across two platforms, but each service is on its best-fit host.

#### Option C: Vercel for everything (with caveats)

Vercel has experimental PHP runtime support via `vercel-php`, but:
- It is community-maintained, not official
- Limited to serverless functions (no persistent sessions)
- Your `SupabaseAuth` relies on `$_SESSION` which won't work in serverless
- Not recommended for this project

#### Option D: Railway or Fly.io

Both support Docker natively and keep services running (no cold starts on paid plans). Similar to Render but with different pricing models.

---

### Phase 6: Production Hardening

Before deploying for real:

1. **Environment variables**: Move all secrets to platform env vars (never `.env` in production)
2. **CORS**: Lock down `Access-Control-Allow-Origin` to your actual frontend domain
3. **HTTPS**: Both Render and Vercel provide this automatically
4. **Auth tokens**: Switch from PHP `$_SESSION` to stateless JWT validation
   - React stores the Supabase token in `localStorage`
   - PHP API validates the token on each request (no server-side session)
   - This is critical for deployment since serverless/stateless hosting can't rely on PHP sessions
5. **Rate limiting**: Add basic rate limiting on the AI endpoint
6. **Error handling**: Return proper JSON error responses, never PHP stack traces

---

## Decision: Do You Even Need PHP Here?

This is the most important question. Here's the honest assessment:

### Architecture Option 1: React + PHP API + Supabase (your plan)

```
React  →  PHP API  →  Supabase
```

### Architecture Option 2: React + Supabase directly (no PHP)

```
React  →  Supabase JS Client (direct)
```

Supabase provides an official JavaScript client (`@supabase/supabase-js`) that handles auth, database queries, and RLS — everything your `SupabaseAuth.php` class does. React could call Supabase directly without PHP in the middle.

**The PHP layer only adds value if:**
- You need server-side business logic (calculations, validation the client shouldn't do)
- You need to hide API keys (Gemini key shouldn't be in frontend code)
- You want to aggregate multiple API calls into one
- You're learning PHP backend development (legitimate educational goal)

**For your AI integration specifically**, PHP is genuinely useful — you don't want the Gemini API key exposed in frontend JavaScript. A PHP endpoint that proxies AI requests is good practice.

---

## My Opinion on This Stack

### Benefits

1. **Separation of concerns**: Frontend and backend can evolve independently. A React dev and a PHP dev can work in parallel.

2. **Your existing PHP knowledge transfers**: Your `SupabaseAuth` class and cURL patterns translate directly into API endpoints. The learning curve is manageable.

3. **Supabase is doing the heavy lifting**: Auth, database, RLS — the hardest parts are already handled. PHP becomes a thin API layer, which keeps complexity low.

4. **Docker deployment works on Render**: Your existing Docker knowledge directly applies. Render makes Docker deployment straightforward.

5. **React is industry-standard**: The skills transfer to jobs, other projects, and the ecosystem is massive.

### Problems / Risks

1. **PHP is a friction point for deployment**. Modern hosting platforms (Vercel, Netlify, Cloudflare Pages) are built around Node.js/serverless. PHP needs Docker, which means Render, Railway, or Fly.io. This limits your options and free tiers.

2. **PHP sessions don't work in stateless hosting**. Your current `SupabaseAuth` uses `$_SESSION` extensively. You'll need to refactor to stateless JWT-based auth where the token comes from the `Authorization` header on every request. This is a significant change to your existing auth flow.

3. **The PHP layer might be mostly pass-through**. If most endpoints just proxy requests to Supabase, you're adding latency and complexity for little benefit. Each request goes: `React → PHP → Supabase → PHP → React` instead of `React → Supabase`.

4. **Two build systems, two runtimes, two deployments**. You need Composer + npm, PHP + Node, and two separate deploy pipelines. More moving parts = more things to debug.

5. **Cold starts on free hosting**. Render's free Docker services sleep after inactivity. A PHP API waking up takes 10-30 seconds, which is a poor user experience. (Supabase direct calls from React wouldn't have this problem.)

6. **CORS complexity**. Cross-origin requests between frontend and backend domains require careful configuration, especially with credentials/cookies. This is a common source of frustration.

### My Recommendation

**If the goal is learning PHP as a backend language:** Go for it. The architecture is valid and educational. Use Slim Framework to keep it lightweight. Deploy backend on Render (Docker) and frontend on Vercel.

**If the goal is building a production app with the least friction:** Drop PHP. Use `React + Supabase JS client` directly, with a few Vercel Edge Functions (or Supabase Edge Functions) for anything that needs a server (like the Gemini API proxy). Everything deploys on Vercel's free tier with zero cold starts.

**The pragmatic middle ground:** Start with the PHP API architecture for learning, but use the `@supabase/supabase-js` client directly in React for auth and simple queries. Keep PHP only for the endpoints that genuinely need a server (AI generation, complex business logic). This gives you the learning value without the overhead on every request.

---

## Migration Order (Recommended)

| Step | Task                                        | Effort   |
| ---- | ------------------------------------------- | -------- |
| 1    | Scaffold repo structure (`api/` + `client/`)| Small    |
| 2    | Set up Slim Framework with one test endpoint| Small    |
| 3    | Port auth flow to stateless JWT validation  | Medium   |
| 4    | Build all API endpoints                     | Medium   |
| 5    | Scaffold React app with Vite + Router       | Small    |
| 6    | Build API service layer in React            | Small    |
| 7    | Build React pages (products, orders, etc.)  | Medium   |
| 8    | Implement auth flow in React                | Medium   |
| 9    | Docker Compose for local dev                | Small    |
| 10   | Deploy API to Render                        | Small    |
| 11   | Deploy React to Vercel or Render            | Small    |
| 12   | Production hardening (CORS, env vars, etc.) | Medium   |
