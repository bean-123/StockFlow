<?php
/**
 * SupabaseAuth - A Simple PHP Class for Supabase Authentication
 *
 * This class handles:
 * - Loading environment variables securely
 * - Google OAuth authentication
 * - Session management
 * - Database queries with user authentication
 *
 * EDUCATIONAL NOTE: This is a simplified implementation for learning.
 * Production applications should use Composer packages like vlucas/phpdotenv
 */

class SupabaseAuth {
    // Supabase configuration (loaded from .env)
    private $supabaseUrl;
    private $supabaseKey;
    private $siteUrl;

    // Current user session
    private $accessToken = null;
    private $user = null;

    /**
     * Constructor - Loads environment variables and starts session
     */
    public function __construct() {
        // Start PHP session for storing auth tokens
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Load environment variables from .env file
        $this->loadEnv();

        // Check if user is already logged in (from session)
        if (isset($_SESSION['supabase_access_token'])) {
            $this->accessToken = $_SESSION['supabase_access_token'];
            $this->user = $_SESSION['supabase_user'] ?? null;
        }
    }

    /**
     * Load environment variables from .env file
     *
     * WHY WE DO THIS:
     * - API keys should NEVER be hardcoded in your PHP files
     * - The .env file is not committed to Git (add to .gitignore)
     * - This keeps your secrets safe when sharing code
     */
    private function loadEnv() {
        // Look for .env in the src folder (one level up from auth folder)
        // This works in Docker where files are served from /var/www/html/
        $envPath = dirname(__DIR__) . '/.env';

        if (!file_exists($envPath)) {
            throw new Exception(
                "Missing .env file! Place your .env file in the src folder (phpDir/src/.env)"
            );
        }

        // Read and parse the .env file
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Store in our object
                switch ($key) {
                    case 'SUPABASE_URL':
                        $this->supabaseUrl = $value;
                        break;
                    case 'SUPABASE_ANON_KEY':
                        $this->supabaseKey = $value;
                        break;
                    case 'SITE_URL':
                        $this->siteUrl = $value;
                        break;
                }
            }
        }

        // Validate required values
        if (empty($this->supabaseUrl) || empty($this->supabaseKey)) {
            throw new Exception("SUPABASE_URL and SUPABASE_ANON_KEY must be set in .env file");
        }
    }

    /**
     * Get the Google Sign-In URL
     *
     * This generates a URL that redirects users to Google's login page.
     * After login, Google sends them back to Supabase, which then sends
     * them to our callback.php with authentication tokens.
     */
    public function getGoogleSignInUrl() {
        // Where to redirect after successful login
        $redirectTo = $this->siteUrl . '/auth/callback.php';

        // Build the Supabase OAuth URL
        $params = http_build_query([
            'provider' => 'google',
            'redirect_to' => $redirectTo
        ]);

        return $this->supabaseUrl . '/auth/v1/authorize?' . $params;
    }

    /**
     * Handle the OAuth callback
     *
     * After Google authentication, Supabase redirects here with tokens
     * in the URL fragment (after the #). We use JavaScript to capture
     * these and send them to PHP.
     */
    public function handleCallback($accessToken, $refreshToken = null) {
        // Store tokens in session
        $_SESSION['supabase_access_token'] = $accessToken;
        if ($refreshToken) {
            $_SESSION['supabase_refresh_token'] = $refreshToken;
        }

        $this->accessToken = $accessToken;

        // Get user info from Supabase
        $user = $this->getUser();
        if ($user) {
            $_SESSION['supabase_user'] = $user;
            $this->user = $user;
        }

        return $user;
    }

    /**
     * Get current user information from Supabase
     */
    public function getUser() {
        if (!$this->accessToken) {
            return null;
        }

        // Call Supabase API to get user info
        $response = $this->makeRequest('GET', '/auth/v1/user');

        if ($response && isset($response['id'])) {
            return $response;
        }

        return null;
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return $this->accessToken !== null && $this->user !== null;
    }

    /**
     * Get cached user data (from session, no API call)
     */
    public function getCurrentUser() {
        return $this->user;
    }

    /**
     * Log out the current user
     */
    public function logout() {
        // Clear Supabase session
        if ($this->accessToken) {
            $this->makeRequest('POST', '/auth/v1/logout');
        }

        // Clear PHP session
        unset($_SESSION['supabase_access_token']);
        unset($_SESSION['supabase_refresh_token']);
        unset($_SESSION['supabase_user']);

        $this->accessToken = null;
        $this->user = null;
    }

    /**
     * Query the database (with authentication)
     *
     * IMPORTANT: Row Level Security (RLS) uses the access token to
     * automatically filter results to only the current user's data.
     *
     * @param string $table  The table to query
     * @param array $params  Query parameters (select, filter, etc.)
     */
    public function query($table, $params = []) {
        // Build query string for PostgREST
        $queryString = '';
        if (!empty($params)) {
            $queryString = '?' . http_build_query($params);
        }

        return $this->makeRequest('GET', '/rest/v1/' . $table . $queryString);
    }

    /**
     * Insert data into a table
     *
     * @param string $table  The table to insert into
     * @param array $data    The data to insert
     */
    public function insert($table, $data) {
        return $this->makeRequest('POST', '/rest/v1/' . $table, $data);
    }

    /**
     * Delete data from a table
     *
     * @param string $table  The table to delete from
     * @param string $filter PostgREST filter (e.g., "id=eq.5")
     */
    public function delete($table, $filter) {
        return $this->makeRequest('DELETE', '/rest/v1/' . $table . '?' . $filter);
    }

    /**
     * Make an HTTP request to Supabase
     *
     * This is the core method that handles all API communication.
     * It adds the required headers for authentication.
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->supabaseUrl . $endpoint;

        // Set up headers
        $headers = [
            'apikey: ' . $this->supabaseKey,
            'Content-Type: application/json',
        ];

        // Add auth header if we have an access token
        if ($this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }

        // For inserts, request the inserted data back
        if ($method === 'POST' && strpos($endpoint, '/rest/v1/') !== false) {
            $headers[] = 'Prefer: return=representation';
        }

        // Initialize cURL
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        // Add request body for POST/PATCH
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Handle errors
        if ($error) {
            throw new Exception("cURL error: " . $error);
        }

        // Parse response
        $decoded = json_decode($response, true);

        // Check for API errors
        if ($httpCode >= 400) {
            $errorMsg = isset($decoded['message']) ? $decoded['message'] :
                       (isset($decoded['error_description']) ? $decoded['error_description'] : $response);
            throw new Exception("Supabase API error ($httpCode): " . $errorMsg);
        }

        return $decoded;
    }
}