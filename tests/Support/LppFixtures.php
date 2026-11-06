<?php

namespace Tests\Support;

/**
 * Builders for LPP API JSON payloads, shaped like https://data.lpp.si/api responses.
 */
class LppFixtures
{
    /**
     * @param  array<int, array{route: string, eta: int, destination?: string}>  $rows
     * @return array{success: bool, data: array{arrivals: array<int, array<string, mixed>>}}
     */
    public static function arrivals(array $rows): array
    {
        return [
            'success' => true,
            'data' => [
                'arrivals' => array_map(fn (array $row): array => [
                    'route_name' => $row['route'],
                    'eta_min' => $row['eta'],
                    'stations' => ['arrival' => $row['destination'] ?? 'KONČNA '.$row['route']],
                ], $rows),
            ],
        ];
    }

    /**
     * @param  array<int, array{code: string, distance: float|int}>  $rows
     * @return array{success: bool, data: array<int, array{ref_id: string, distance: float|int}>}
     */
    public static function stationsInRange(array $rows): array
    {
        return [
            'success' => true,
            'data' => array_map(fn (array $row): array => [
                'ref_id' => $row['code'],
                'distance' => $row['distance'],
            ], $rows),
        ];
    }

    /**
     * @param  array<int, array{id: int, code: string, name: string}>  $rows
     * @return array{success: bool, data: array<int, array<string, mixed>>}
     */
    public static function allStations(array $rows): array
    {
        return [
            'success' => true,
            'data' => array_map(fn (array $row): array => [
                'int_id' => $row['id'],
                'ref_id' => $row['code'],
                'name' => $row['name'],
                'route_groups_on_station' => ['1'],
            ], $rows),
        ];
    }
}
