<?php

namespace Database\Seeders;

use App\Models\Station;
use App\Services\LppApiService;
use App\Traits\CachesStations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

class LoadStations extends Seeder
{
    use CachesStations;

    public function __construct(
        private readonly LppApiService $api
    ) {}

    public function run(): void
    {
        // Step 1: Load/update all stations from API
        $this->loadStations();

        // Step 2: Process stations by matching names
        $this->processIncompleteStations();

        // Step 3: Find sequential stations that might be opposites
        $this->findSequentialStations();
    }

    // Helper method for console output
    private function info(string $message): void
    {
        if ($this->command) {
            $this->command->info($message);
        } else {
            // Fallback to log if command isn't available
            Log::info($message);
        }
    }

    // Setter for command object
    public function setCommand(Command $command): self
    {
        $this->command = $command;
        return $this;
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

            // Invalidate station cache
            $this->invalidateStationCache($stationData['ref_id']);
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
                $this->info("Name matched: {$station->name} ({$station->id} ↔ {$opposite->name} {$opposite->id})");
            }
        }
    }

    private function findSequentialStations(): void
    {
        $this->info("Looking for sequential station codes...");

        // Get stations without opposites
        $incompleteStations = Station::whereNull('opposite_station_id')->get();

        // Group stations by their code, excluding nulls or empty codes
        $stationsByCode = [];
        foreach ($incompleteStations as $station) {
            if (!empty($station->code)) {
                $stationsByCode[$station->code] = $station;
            }
        }

        // Sort by code to make identification easier
        ksort($stationsByCode);
        $codes = array_keys($stationsByCode);

        $potentialPairs = [];
        $linkedCount = 0;

        // Look for sequential codes (e.g., 601011 and 601012)
        foreach ($codes as $code) {
            // Refresh the station list after potential linking
            if ($linkedCount > 0) {
                $refreshedStations = Station::whereNull('opposite_station_id')->get();
                $stationsByCode = [];
                foreach ($refreshedStations as $station) {
                    if (!empty($station->code)) {
                        $stationsByCode[$station->code] = $station;
                    }
                }
                $codes = array_keys($stationsByCode);
                $linkedCount = 0;
            }

            // Skip if code no longer exists (may have been linked already)
            if (!isset($stationsByCode[$code])) {
                continue;
            }

            // Calculate potential sequential codes (±1)
            $nextCode = (string)((int)$code + 1);
            $prevCode = (string)((int)$code - 1);

            // Check if the next sequential code exists
            if (isset($stationsByCode[$nextCode])) {
                $station = $stationsByCode[$code];
                $oppositeStation = $stationsByCode[$nextCode];

                // Only link if neither station has an opposite yet
                if (!$station->opposite_station_id && !$oppositeStation->opposite_station_id) {
                    $this->linkStations($station, $oppositeStation);
                    $this->info("Linked sequential codes: {$station->name} (code: {$station->code}) ↔ {$oppositeStation->name} (code: {$oppositeStation->code})");
                    $linkedCount++;

                    // Remove these stations from our list to avoid trying to link them again
                    unset($stationsByCode[$code]);
                    unset($stationsByCode[$nextCode]);
                }
            }
            // Check if previous sequential code exists (for thoroughness)
            else if (isset($stationsByCode[$prevCode])) {
                $station = $stationsByCode[$code];
                $oppositeStation = $stationsByCode[$prevCode];

                // Only link if neither station has an opposite yet
                if (!$station->opposite_station_id && !$oppositeStation->opposite_station_id) {
                    $this->linkStations($station, $oppositeStation);
                    $this->info("Linked sequential codes: {$station->name} (code: {$station->code}) ↔ {$oppositeStation->name} (code: {$oppositeStation->code})");
                    $linkedCount++;

                    // Remove these stations from our list to avoid trying to link them again
                    unset($stationsByCode[$code]);
                    unset($stationsByCode[$prevCode]);
                }
            }
        }

        // Summary info
        if ($linkedCount > 0) {
            $this->info("Linked {$linkedCount} pairs of stations with sequential codes.");
        } else {
            $this->info("No new sequential station pairs were linked.");
        }
    }

    private function pairExists(array $codePair, array $pairs): bool
    {
        foreach ($pairs as $pair) {
            $existingCodes = [
                $pair['station1']->code,
                $pair['station2']->code
            ];

            sort($existingCodes);
            sort($codePair);

            if ($existingCodes === $codePair) {
                return true;
            }
        }

        return false;
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
