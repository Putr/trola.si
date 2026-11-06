<?php

namespace App\Console\Commands;

use App\Models\Station;
use App\Repositories\StationRepository;
use Illuminate\Console\Command;

class WarmStationCache extends Command
{
    protected $signature = 'stations:warm-cache';

    protected $description = 'Refresh the cached lookups for every station';

    public function handle(StationRepository $stations): int
    {
        $count = 0;

        Station::query()->select(['id', 'code'])->lazyById()->each(function (Station $station) use ($stations, &$count): void {
            $stations->forget($station);
            $stations->findById($station->id);

            if ($station->code !== null) {
                $stations->findByCode($station->code);
            }

            $count++;
        });

        $this->info("Warmed the cache for {$count} stations.");

        return self::SUCCESS;
    }
}
