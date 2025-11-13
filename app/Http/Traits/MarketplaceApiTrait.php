<?php

namespace App\Http\Traits;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait MarketplaceApiTrait
{
    /**
     * Perform a GET request to a marketplace API.
     *
     * @param string $baseUrl
     * @param string $endpoint
     * @param array $params
     * @param string|null $token
     * @return array
     */
    public function getMarketplaceData(string $baseUrl, string $endpoint, array $params = [], ?string $token = null): array
    {
        try {
            $response = Http::withHeaders($this->buildHeaders($token))
                ->get($this->buildUrl($baseUrl, $endpoint), $params);

            return $this->handleResponse($response);
        } catch (\Throwable $e) {
            Log::error('[Marketplace GET Error]', [
                'url' => $baseUrl . $endpoint,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform a POST request to a marketplace API.
     *
     * @param string $baseUrl
     * @param string $endpoint
     * @param array $body
     * @param string|null $token
     * @return array
     */
    public function postMarketplaceData(string $baseUrl, string $endpoint, array $body = [], ?string $token = null): array
    {
        try {
            $response = Http::withHeaders($this->buildHeaders($token))
                ->post($this->buildUrl($baseUrl, $endpoint), $body);

            return $this->handleResponse($response);
        } catch (\Throwable $e) {
            Log::error('[Marketplace POST Error]', [
                'url' => $baseUrl . $endpoint,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Common response handler.
     */
    protected function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return [
                'success' => true,
                'status' => $response->status(),
                'data' => $response->json(),
            ];
        }

        // Log unexpected failures
        Log::warning('[Marketplace API Failed]', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [
            'success' => false,
            'status' => $response->status(),
            'error' => $response->json() ?: $response->body(),
        ];
    }

    /**
     * Builds a complete URL safely.
     */
    protected function buildUrl(string $baseUrl, string $endpoint): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * Builds headers with optional bearer token.
     */
    protected function buildHeaders(?string $token): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}
