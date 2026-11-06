<?php

namespace Database\Seeders;

use App\Actions\SyncStations;
use Illuminate\Database\Seeder;

class LoadStations extends Seeder
{
    public function run(SyncStations $syncStations): void
    {
        $result = $syncStations();

        $this->command?->info("Synced {$result->upserted} stations ({$result->paired} paired).");
    }
}
