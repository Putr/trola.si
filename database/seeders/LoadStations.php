<?php

namespace Database\Seeders;

use App\Models\Station;
use App\Services\LppApiService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class LoadStations extends Seeder
{
    public function __construct(
        private readonly LppApiService $api
    ) {}

    public function run(): void
    {
        // Step 1: Load/update all stations from API
        $this->loadStations();

        // Step 2: Process stations by matching names
        $this->processIncompleteStations();
    }

    private function loadStations(): void
    {
        $stations = $this->api->getAllStations();

        if (!$stations) {
            Log::error('Failed to fetch stations from LPP API');
            return;
        }

        foreach ($stations as $stationData) {
            Station::updateOrCreate(
                ['id' => $stationData['int_id']],
                [
                    'code' => $stationData['ref_id'],
                    'name' => $stationData['name'],
                    'routes' => $stationData['route_groups_on_station'] ?? [],
                    'opposite_station_id' => null,
                    'is_direction_to_center' => null,
                ]
            );
        }
    }

    private function processIncompleteStations(): void
    {
        $incompleteStations = Station::all();

        foreach ($incompleteStations as $station) {
            $station->refresh();
            if ($station->opposite_station_id) {
                continue;
            }

            $opposite = Station::where('name', $station->name)
                ->where('id', '!=', $station->id)
                ->first();

            if ($opposite) {
                $this->linkStations($station, $opposite);
                $this->command->info("Name matched: {$station->name} ({$station->id} ↔ {$opposite->name} {$opposite->id})");
            }
        }
    }

    private function linkStations(Station $station, Station $opposite): void
    {
        // Determine direction based on ID (lower = closer to center)
        $isToCenter = $station->id < $opposite->id;

        // Update current station
        $station->update([
            'opposite_station_id' => $opposite->id,
            'is_direction_to_center' => $isToCenter,
        ]);

        // Update opposite station
        $opposite->update([
            'opposite_station_id' => $station->id,
            'is_direction_to_center' => !$isToCenter,
        ]);
    }
}
