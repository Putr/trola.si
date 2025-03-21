<?php

namespace App\Console\Commands;

use App\Models\Station;
use App\Traits\CachesStations;
use Illuminate\Console\Command;

class WarmStationCache extends Command
{
    use CachesStations;

    protected $signature = 'stations:warm-cache';
    protected $description = 'Warm up the station cache by pre-caching all stations';

    public function handle()
    {
        $this->info('Starting to warm up station cache...');

        $stations = Station::select(['id', 'code', 'name', 'is_direction_to_center', 'opposite_station_id'])->get();
        $count = 0;

        foreach ($stations as $station) {
            // Cache station data for 1 day
            $this->getStationFromCache($station->code);

            $count++;
            if ($count % 100 === 0) {
                $this->info("Processed {$count} stations...");
            }
        }

        $this->info("Successfully warmed up cache for {$count} stations.");
    }
}
