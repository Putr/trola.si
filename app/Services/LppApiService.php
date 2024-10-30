<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
        $cacheKey = "lpp_api_" . md5($endpoint . $method);

        return Cache::remember($cacheKey, 30, function () use ($endpoint, $method) {
            return Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->$method("{$this->baseUrl}/{$endpoint}");
        });
    }
}
