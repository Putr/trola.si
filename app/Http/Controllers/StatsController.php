<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Routing\Controller as BaseController;

class StatsController extends BaseController
{
    public function index()
    {
        $stats = [];

        // Get stats for last 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::now()->subDays($i)->format('Y_m_d');

            // Get basic page stats
            $homeViews = (int)Redis::get("stats_{$date}_home") ?? 0;
            $searchViews = (int)Redis::get("stats_{$date}_search") ?? 0;
            $geosearchViews = (int)Redis::get("stats_{$date}_geosearch") ?? 0;

            // Get all station views for this day
            $stationKeys = Redis::keys("stats_{$date}_station_*");
            $stationViews = 0;
            foreach ($stationKeys as $key) {
                $key = str_replace('trolasi_database_', '', $key);
                $stationViews += (int)Redis::get($key);
            }

            $stats[] = [
                'date' => Carbon::createFromFormat('Y_m_d', $date)->format('Y-m-d'),
                'home' => $homeViews,
                'search' => $searchViews,
                'geosearch' => $geosearchViews,
                'stations' => $stationViews,
            ];
        }

        return view('stats', [
            'stats' => collect($stats)
        ]);
    }
}
