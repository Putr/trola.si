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
            'station' => $station
        ]);
    }
}
