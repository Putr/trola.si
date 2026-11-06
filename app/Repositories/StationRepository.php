<?php

namespace App\Repositories;

use App\Models\Station;
use Illuminate\Support\Facades\Cache;

/**
 * Cached station lookups. Stations change at most once a day (nightly sync),
 * so lookups are cached for a day and invalidated by the sync.
 */
class StationRepository
{
    private const TTL_SECONDS = 86400;

    /** @var list<string> */
    private const COLUMNS = ['id', 'code', 'name', 'is_direction_to_center', 'opposite_station_id'];

    public function findByCode(string $code): ?Station
    {
        return Cache::remember(
            $this->codeKey($code),
            self::TTL_SECONDS,
            fn (): ?Station => Station::query()->select(self::COLUMNS)->where('code', $code)->first(),
        );
    }

    public function findById(int $id): ?Station
    {
        return Cache::remember(
            $this->idKey($id),
            self::TTL_SECONDS,
            fn (): ?Station => Station::query()->select(self::COLUMNS)->find($id),
        );
    }

    public function forget(Station $station): void
    {
        Cache::forget($this->idKey($station->id));

        if ($station->code !== null) {
            Cache::forget($this->codeKey($station->code));
        }

        $previousCode = $station->getOriginal('code');
        if (is_string($previousCode) && $previousCode !== $station->code) {
            Cache::forget($this->codeKey($previousCode));
        }
    }

    private function codeKey(string $code): string
    {
        return "station:code:{$code}";
    }

    private function idKey(int $id): string
    {
        return "station:id:{$id}";
    }
}
