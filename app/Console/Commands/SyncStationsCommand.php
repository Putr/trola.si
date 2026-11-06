<?php

namespace App\Console\Commands;

use App\Actions\SyncStations;
use Illuminate\Console\Command;
use RuntimeException;

class SyncStationsCommand extends Command
{
    protected $signature = 'stations:sync';

    protected $description = 'Sync stations from the LPP API and infer directions and opposite stations';

    public function handle(SyncStations $syncStations): int
    {
        try {
            $result = $syncStations();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->info("Synced {$result->upserted} stations: {$result->paired} paired, {$result->unpaired} without an opposite.");

        return self::SUCCESS;
    }
}
