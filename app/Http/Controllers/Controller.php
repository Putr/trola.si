<?php

namespace App\Http\Controllers;

use App\Models\Station;
use App\Services\LppApiService;

class Controller
{
    public function index()
    {
        return view('index');
    }

    public function show(string $stopId)
    {
        $station = Station::where('code', $stopId)->first();

        if (!$station) {
            return view('station-not-found');
        }

        return view('station', [
            'station' => $station,
            'arrivals' => $station->arrivals,
            'directionToCenter' => $station->is_direction_to_center,
            'hrefs' => [
                'to' => '/' . ($station->is_direction_to_center ? $station->code : $station->oppositeStationCode),
                'from' => '/' . ($station->is_direction_to_center ? $station->oppositeStationCode : $station->code),
                'all' => '/' . $station->code . '/all'
            ]
        ]);
    }

    public function showBothDirections(string $stopId)
    {
        $station = Station::where('code', $stopId)->first();
        $oppositeStation = Station::where('code', $station->oppositeStationCode)->first();

        $arrivals = collect(array_merge($station->arrivals, $oppositeStation->arrivals))
            ->sortBy('route_name_numeric')
            ->values()
            ->all();

        return view('station', [
            'station' => $station,
            'arrivals' => $arrivals,
            'directionToCenter' => null,
            'hrefs' => [
                'to' => '/' . ($station->is_direction_to_center ? $station->code : $oppositeStation->code),
                'from' => '/' . ($station->is_direction_to_center ? $oppositeStation->code : $station->code),
                'all' => '/' . $station->code . '/all'
            ]
        ]);
    }
}
