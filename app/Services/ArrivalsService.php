<?php

namespace App\Services;

use App\Models\Station;

/**
 * Turns the LPP arrivals feed for a station into one row per route, ready for the views.
 */
class ArrivalsService
{
    /** The first arrival is highlighted only when it is at most this many minutes away. */
    public const HIGHLIGHT_ETA_MAX = 9;

    public function __construct(private readonly LppApiService $api) {}

    /**
     * @return array<int, array{routeName: string, routeDirectionName: string, route_name_numeric: int, etas: list<int>, etaMin: ?int, laterEtas: list<int>}>
     */
    public function forStation(Station $station): array
    {
        $arrivals = $this->api->getStationArrivals((string) $station->code)['arrivals'] ?? [];

        $routes = [];
        foreach ($arrivals as $arrival) {
            $routeName = (string) $arrival['route_name'];
            $routes[$routeName] ??= [
                'routeName' => $routeName,
                'routeDirectionName' => (string) ($arrival['stations']['arrival'] ?? ''),
                'route_name_numeric' => (int) preg_replace('/\D/', '', $routeName),
                'etas' => [],
            ];
            $routes[$routeName]['etas'][] = (int) $arrival['eta_min'];
        }

        $rows = [];
        foreach ($routes as $route) {
            sort($route['etas']);
            $highlight = $route['etas'][0] <= self::HIGHLIGHT_ETA_MAX;

            $rows[] = [
                ...$route,
                'etaMin' => $highlight ? $route['etas'][0] : null,
                'laterEtas' => $highlight ? array_slice($route['etas'], 1) : $route['etas'],
            ];
        }

        usort($rows, fn (array $a, array $b): int => $a['route_name_numeric'] <=> $b['route_name_numeric']);

        return $rows;
    }
}
