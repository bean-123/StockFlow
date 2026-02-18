<?php
/**
 * GeminiAI - Simple PHP class for Google Gemini API
 *
 * A minimal implementation for making requests to the Gemini API.
 */

class GeminiAI {
    private $apiKey;
    // Model endpoint
    private $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent';

    public function __construct() {
        $this->loadApiKey();
    }

    /**
     * Load API key from .env file
     */
    private function loadApiKey() {
        $envPath = dirname(__DIR__) . '/.env';

        if (!file_exists($envPath)) {
            throw new Exception("Missing .env file!");
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;

            if (strpos($line, 'GEMINI_API_KEY=') === 0) {
                $this->apiKey = trim(substr($line, strlen('GEMINI_API_KEY=')));
                break;
            }
        }

        if (empty($this->apiKey) || $this->apiKey === 'your-gemini-api-key-here') {
            throw new Exception("Please add your Gemini API key to the .env file");
        }
    }

    /**
     * Send a prompt to Gemini and get a response
     */
    public function ask($prompt) {
        $url = $this->apiUrl . '?key=' . $this->apiKey;

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Request failed: " . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $decoded['error']['message'] ?? $response;
            throw new Exception("API error: " . $errorMsg);
        }

        // Extract the text response
        return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? 'No response';
    }
}