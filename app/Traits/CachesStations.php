<?php

namespace App\Traits;

use App\Models\Station;
use Illuminate\Support\Facades\Cache;

trait CachesStations
{
    /**
     * Get station from cache or database
     */
    protected function getStationFromCache(string $code): ?Station
    {
        return Cache::remember("station:{$code}", 86400, function () use ($code) {
            return Station::select(['id', 'code', 'name', 'is_direction_to_center', 'opposite_station_id'])
                ->where('code', $code)
                ->first();
        });
    }

    /**
     * Get station by ID from cache or database
     */
    protected function getStationByIdFromCache(int $id): ?Station
    {
        return Cache::remember("station:{$id}", 86400, function () use ($id) {
            return Station::select(['id', 'code', 'name', 'is_direction_to_center', 'opposite_station_id'])
                ->where('id', $id)
                ->first();
        });
    }

    /**
     * Get station arrivals from cache or database
     */
    protected function getStationArrivalsFromCache(Station $station): array
    {
        return Cache::remember("arrivals:{$station->code}", 30, function () use ($station) {
            return $station->arrivals;
        });
    }

    /**
     * Invalidate station cache
     */
    protected function invalidateStationCache(string $code): void
    {
        Cache::forget("station:{$code}");
        Cache::forget("arrivals:{$code}");
    }

    /**
     * Invalidate station cache by ID
     */
    protected function invalidateStationCacheById(int $id): void
    {
        Cache::forget("station:{$id}");
    }
}
