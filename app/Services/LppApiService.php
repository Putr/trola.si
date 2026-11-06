<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the LPP open data API (https://data.lpp.si/api).
 *
 * Successful responses are cached briefly so a burst of visitors on one station
 * costs a single upstream call. Failures are logged and returned as null.
 */
class LppApiService
{
    public const CACHE_SECONDS = 30;

    private const TIMEOUT_SECONDS = 5;

    /** The full station list is the largest payload and only fetched by the nightly sync. */
    private const SYNC_TIMEOUT_SECONDS = 60;

    /** Station used to check that the API responds (Bavarski dvor). */
    private const PING_STATION_CODE = '600011';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function getStationArrivals(string $stationCode): ?array
    {
        return $this->get('/station/arrival', ['station-code' => $stationCode]);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function getStationsInRange(float $latitude, float $longitude, int $radius = 500): ?array
    {
        return $this->get('/station/stations-in-range', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius,
        ]);
    }

    /**
     * Every station with its routes. Never cached: the nightly sync must see fresh data.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function getAllStations(): ?array
    {
        return $this->fetch('/station/station-details', ['show-subroutes' => 1], self::SYNC_TIMEOUT_SECONDS);
    }

    public function ping(): bool
    {
        return $this->get('/station/arrival', ['station-code' => self::PING_STATION_CODE], cache: false) !== null;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<mixed>|null
     */
    private function get(string $path, array $query, bool $cache = true): ?array
    {
        if (! $cache) {
            return $this->fetch($path, $query);
        }

        $key = 'lpp_api_'.md5($path.'?'.http_build_query($query));

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $data = $this->fetch($path, $query);
        if ($data !== null) {
            Cache::put($key, $data, self::CACHE_SECONDS);
        }

        return $data;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<mixed>|null
     */
    private function fetch(string $path, array $query, int $timeout = self::TIMEOUT_SECONDS): ?array
    {
        try {
            $response = $this->client()->timeout($timeout)->get(ltrim($path, '/'), $query);
        } catch (ConnectionException $e) {
            Log::warning('LPP API unreachable', ['path' => $path, 'query' => $query, 'error' => $e->getMessage()]);

            return null;
        }

        $data = $response->json('data');

        if (! $response->successful() || ! is_array($data)) {
            Log::warning('LPP API request failed', [
                'path' => $path,
                'query' => $query,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        return $data;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/').'/')
            ->withToken($this->apiKey)
            ->acceptJson();
    }
}
