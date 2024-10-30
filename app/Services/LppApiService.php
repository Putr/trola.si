<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LppApiService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('LPP_API_URL'), '/');
        $this->apiKey = env('LPP_API_KEY');
    }



    public function ping(): bool
    {
        try {
            $response = $this->callApi('/station/arrival?station-code=600011');
            return (bool) $response->successful();
        } catch (\Exception $e) {
            Log::error('LPP API Ping failed: ' . $e->getMessage());
            return false;
        }
    }

    //
    // UTILITY
    //

    private function callApi(string $endpoint, string $method = 'GET')
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->$method("{$this->baseUrl}/{$endpoint}");
    }
}
