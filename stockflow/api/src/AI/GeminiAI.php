<?php

namespace StockFlow\AI;

/**
 * GeminiAI - PHP class for Google Gemini API
 *
 * Adapted from the original phpDir implementation.
 * Key difference: uses $_ENV (loaded by phpdotenv) instead of manually parsing .env
 */
class GeminiAI
{
    private string $apiKey;
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = $_ENV['GEMINI_API_KEY'] ?? '';

        if (empty($this->apiKey)) {
            throw new \Exception('GEMINI_API_KEY is not set in .env');
        }
    }

    /**
     * Send a prompt to Gemini and get a text response
     *
     * @param string $prompt  The question or instruction to send
     * @return string         The AI's text response
     */
    public function ask(string $prompt): string
    {
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

        if ($error) {
            throw new \Exception("Gemini request failed: " . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $decoded['error']['message'] ?? $response;
            throw new \Exception("Gemini API error: " . $errorMsg);
        }

        return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? 'No response';
    }
}
