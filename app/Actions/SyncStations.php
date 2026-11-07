<?php

namespace App\Actions;

use App\Models\Station;
use App\Repositories\StationRepository;
use App\Services\LppApiService;
use App\Support\StationName;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pulls all stations from the LPP API and infers which stations are opposite
 * each other and which one heads toward the city centre.
 *
 * The LPP API has no notion of direction, so this is a heuristic:
 *  1. stations with the same name are opposites;
 *  2. remaining stations with sequential codes (±1) are opposites;
 *  3. of a pair, the code ending in "1" heads toward the centre; otherwise the lower code does.
 *
 * Everything is computed in memory and written in a single transaction, so a
 * station never has a half-synced (null) pairing visible to visitors.
 */
class SyncStations
{
    public function __construct(
        private readonly LppApiService $api,
        private readonly StationRepository $stations,
    ) {}

    public function __invoke(): SyncResult
    {
        $payload = $this->api->getAllStations();

        if (empty($payload)) {
            throw new RuntimeException('LPP API returned no stations; existing stations left untouched.');
        }

        /** @var array<int, array{id: int, code: string, name: string, routes: array<mixed>, opposite_station_id: ?int, is_direction_to_center: ?bool}> $rows */
        $rows = [];
        foreach ($payload as $item) {
            $id = (int) $item['int_id'];
            $rows[$id] = [
                'id' => $id,
                'code' => (string) $item['ref_id'],
                'name' => (string) $item['name'],
                'routes' => $item['route_groups_on_station'] ?? [],
                'opposite_station_id' => null,
                'is_direction_to_center' => null,
            ];
        }
        ksort($rows);

        $this->pairByName($rows);
        $this->pairBySequentialCode($rows);

        $saved = DB::transaction(function () use ($rows): array {
            // New stations go in unpaired first so the opposite_station_id foreign key
            // can point at them; none of this is visible outside the transaction.
            $existingIds = Station::query()->whereKey(array_keys($rows))->pluck('id')->all();
            foreach (array_diff(array_keys($rows), $existingIds) as $newId) {
                Station::query()->create([...$rows[$newId], 'opposite_station_id' => null, 'is_direction_to_center' => null]);
            }

            $saved = [];
            foreach ($rows as $row) {
                $station = Station::query()->findOrFail($row['id']);
                $station->fill($row)->save();
                $saved[] = $station;
            }

            return $saved;
        });

        foreach ($saved as $station) {
            $this->stations->forget($station);
        }

        $paired = count(array_filter($rows, fn (array $row): bool => $row['opposite_station_id'] !== null));

        return new SyncResult(upserted: count($rows), paired: $paired, unpaired: count($rows) - $paired);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function pairByName(array &$rows): void
    {
        $keys = array_map(fn (array $row): string => StationName::key($row['name']), $rows);

        foreach ($rows as $id => $row) {
            if ($rows[$id]['opposite_station_id'] !== null || $keys[$id] === '') {
                continue;
            }

            foreach ($rows as $otherId => $other) {
                if ($otherId !== $id && $other['opposite_station_id'] === null && $keys[$otherId] === $keys[$id]) {
                    $this->link($rows, $id, $otherId);
                    break;
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function pairBySequentialCode(array &$rows): void
    {
        /** @var array<string, int> $unpairedIdByCode */
        $unpairedIdByCode = [];
        foreach ($rows as $id => $row) {
            if ($row['opposite_station_id'] === null && ctype_digit($row['code'])) {
                $unpairedIdByCode[$row['code']] = $id;
            }
        }
        ksort($unpairedIdByCode, SORT_NUMERIC);

        foreach (array_keys($unpairedIdByCode) as $code) {
            $code = (string) $code;
            if (! isset($unpairedIdByCode[$code])) {
                continue;
            }

            foreach ([(string) ((int) $code + 1), (string) ((int) $code - 1)] as $neighbour) {
                if (isset($unpairedIdByCode[$neighbour])) {
                    $this->link($rows, $unpairedIdByCode[$code], $unpairedIdByCode[$neighbour]);
                    unset($unpairedIdByCode[$code], $unpairedIdByCode[$neighbour]);
                    break;
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function link(array &$rows, int $id, int $otherId): void
    {
        $code = $rows[$id]['code'];
        $otherCode = $rows[$otherId]['code'];
        $endsWithOne = str_ends_with($code, '1');

        $isToCenter = $endsWithOne !== str_ends_with($otherCode, '1')
            ? $endsWithOne
            : (int) $code < (int) $otherCode;

        $rows[$id]['opposite_station_id'] = $otherId;
        $rows[$id]['is_direction_to_center'] = $isToCenter;
        $rows[$otherId]['opposite_station_id'] = $id;
        $rows[$otherId]['is_direction_to_center'] = ! $isToCenter;
    }
}

final readonly class SyncResult
{
    public function __construct(
        public int $upserted,
        public int $paired,
        public int $unpaired,
    ) {}
}
