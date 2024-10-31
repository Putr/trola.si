<?php

namespace App\Http\Controllers;

use App\Services\LppApiService;

class Controller
{
    public function index()
    {
        return view('index');
    }

    public function show(string $stopId, LppApiService $lppService)
    {
        list($station, $arrivals) = $lppService->getStationArrivals($stopId);
        dd($station, $arrivals);
        return view('station', [
            'station' => $station,
            'arrivals' => $arrivals,
        ]);
    }
}
