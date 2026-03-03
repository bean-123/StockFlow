<?php

namespace StockFlow\Auth;

/**
 * SupabaseAuth - Stateless PHP client for Supabase
 *
 * Compared to the original (phpDir/src/auth/SupabaseAuth.php):
 * - No $_SESSION — token is passed in per-request via setToken()
 * - No loadEnv() — phpdotenv in index.php already loads $_ENV
 * - No debug logging or HTML rendering — this is a pure API class
 *
 * The core cURL logic (makeRequest) is almost identical to the original.
 */

class SupabaseAuth
{
    private string $supabaseUrl;
    private string $supabaseKey;
    private string $siteUrl;
    private ?string $accessToken = null;

    public function __construct()
    {
        // $_ENV is populated by phpdotenv in index.php — no manual parsing needed
        $this->supabaseUrl = $_ENV['SUPABASE_URL'];
        $this->supabaseKey = $_ENV['SUPABASE_ANON_KEY'];
        $this->siteUrl = $_ENV['SITE_URL'];
    }

    /**
     * Set the access token for this request.
     * Called by AuthMiddleware with the Bearer token from the Authorization header.
     * In the original, this came from $_SESSION. Now it comes from the request.
     */
    public function setToken(string $token): void
    {
        $this->accessToken = $token;
    }

    /**
     * Build the Google OAuth sign-in URL.
     * Identical to the original — just returns a URL string.
     */
    public function getGoogleSignInUrl(): string
    {
        $params = http_build_query([
            'provider' => 'google',
            'redirect_to' => $this->siteUrl . '/auth/callback'
        ]);

        return $this->supabaseUrl . '/auth/v1/authorize?' . $params;
    }

    /**
     * Get user info from Supabase using the current access token.
     * Identical to the original.
     */
    public function getUser(): ?array
    {
        if (!$this->accessToken) {
            return null;
        }

        $response = $this->makeRequest('GET', '/auth/v1/user');

        if ($response && isset($response['id'])) {
            return $response;
        }

        return null;
    }

    /**
     * Tell Supabase to invalidate the token.
     * Simplified — no $_SESSION cleanup needed.
     */
    public function logout(): void
    {
        if ($this->accessToken) {
            $this->makeRequest('POST', '/auth/v1/logout');
        }
    }

    /**
     * Query a table. Identical to the original.
     * RLS still works — Supabase reads the Bearer token to filter rows.
     */
    public function query(string $table, array $params = []): array
    {
        $queryString = '';
        if (!empty($params)) {
            $queryString = '?' . http_build_query($params);
        }

        return $this->makeRequest('GET', '/rest/v1/' . $table . $queryString);
    }

    /** Insert into a table. Identical to the original. */
    public function insert(string $table, array $data): array
    {
        return $this->makeRequest('POST', '/rest/v1/' . $table, $data);
    }

    /** Delete from a table. Identical to the original. */
    public function delete(string $table, string $filter): array
    {
        return $this->makeRequest('DELETE', '/rest/v1/' . $table . '?' . $filter);
    }

    /**
     * Make an HTTP request to Supabase.
     * This is the same cURL logic from the original, minus the debug logging.
     */
    private function makeRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->supabaseUrl . $endpoint;

        $headers = [
            'apikey: ' . $this->supabaseKey,
            'Content-Type: application/json',
        ];

        if ($this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }

        // For inserts, tell Supabase to return the created row
        if ($method === 'POST' && strpos($endpoint, '/rest/v1/') !== false) {
            $headers[] = 'Prefer: return=representation';
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        // curl_close() removed — deprecated since PHP 8.0, no-op since 8.5.
        // cURL handles are closed automatically when they go out of scope.

        if ($error) {
            throw new \Exception("cURL error: " . $error);
        }

        $decoded = json_decode($response, true) ?? [];

        if ($httpCode >= 400) {
            $errorMsg = $decoded['message']
                ?? $decoded['error_description']
                ?? $response;
            throw new \Exception("Supabase error ($httpCode): " . $errorMsg);
        }

        return $decoded;
    }
}