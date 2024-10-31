<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Env;

class LppApiService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('LPP_API_URL'), '/');
        $this->apiKey = env('LPP_API_KEY');
    }

    public function getStationArrivals(string $stationCode): array|null
    {
        return $this->safeApiCall(
            "Station Arrivals",
            "/station/arrival?station-code={$stationCode}",
            ['station_id' => $stationCode]
        );
    }

    public function getStationDetails(string $stationCode): array|null
    {
        return $this->safeApiCall(
            "Station Details",
            "/station/station-details?station-code={$stationCode}&show-subroutes=1",
            ['station_id' => $stationCode]
        );
    }

    public function getRoutesOnStation(string $stationCode): array|null
    {
        return $this->safeApiCall(
            "Routes On Station",
            "/station/routes-on-station?station-code={$stationCode}",
            ['station_id' => $stationCode]
        );
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

    public function getAllStations(): array|null
    {
        return $this->safeApiCall(
            "All Stations",
            "/station/station-details?show-subroutes=1",
            []
        );
    }

    //
    // UTILITY
    //

    private function safeApiCall(string $operation, string $endpoint, array $context = []): array|null
    {
        try {
            $response = $this->callApi($endpoint);

            if (!$response->successful()) {
                Log::error("LPP API {$operation} failed", [
                    ...$context,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            return $data['data'];
        } catch (\Exception $e) {
            Log::error("LPP API {$operation} failed: " . $e->getMessage(), $context);
            return null;
        }
    }

    private function callApi(string $endpoint, string $method = 'GET'): Response
    {
        $cacheKey = "lpp_api_" . md5($endpoint . $method);

        return Cache::remember($cacheKey, 30, function () use ($endpoint, $method) {
            return Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->$method("{$this->baseUrl}/{$endpoint}");
        });
    }
}
