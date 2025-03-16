<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Station;
use Illuminate\Support\Facades\Log;

class StatsController extends BaseController
{
    public function index()
    {
        $stats = [];
        $popularPages = $this->getPopularPages();

        // Get stats for last 14 days
        for ($i = 0; $i < 14; $i++) {
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
            'stats' => collect($stats),
            'popularPages' => $popularPages
        ]);
    }

    /**
     * Get the most popular pages from the last 7 days
     *
     * @return \Illuminate\Support\Collection
     */
    private function getPopularPages()
    {
        $popularPages = collect();
        $stationMap = [];

        // Get all station data for lookup
        $stations = Station::all()->keyBy('code');

        // Get data for the last 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::now()->subDays($i)->format('Y_m_d');

            // Process home page views
            $homeViews = (int)Redis::get("stats_{$date}_home") ?? 0;
            if ($homeViews > 0) {
                $popularPages->put('home', ($popularPages->get('home', 0) + $homeViews));
            }

            // Process search page views
            $searchViews = (int)Redis::get("stats_{$date}_search") ?? 0;
            if ($searchViews > 0) {
                $popularPages->put('search', ($popularPages->get('search', 0) + $searchViews));
            }

            // Process geosearch page views
            $geosearchViews = (int)Redis::get("stats_{$date}_geosearch") ?? 0;
            if ($geosearchViews > 0) {
                $popularPages->put('geosearch', ($popularPages->get('geosearch', 0) + $geosearchViews));
            }

            // Process not found page views
            $notFoundViews = (int)Redis::get("stats_{$date}_not_found") ?? 0;
            if ($notFoundViews > 0) {
                $popularPages->put('not_found', ($popularPages->get('not_found', 0) + $notFoundViews));
            }

            // Process station page views
            $stationKeys = Redis::keys("stats_{$date}_station_*");

            foreach ($stationKeys as $redisKey) {
                // Get the clean key without Redis prefix
                $key = str_replace('trolasi_database_', '', $redisKey);
                $views = (int)Redis::get($key);

                if ($views <= 0) {
                    continue;
                }

                // Use regex to extract station code and check if it's a "both directions" view
                if (preg_match('/^stats_[0-9_]+_station_([0-9]+)(?:_all)?$/', $key, $matches)) {
                    $stationCode = $matches[1];
                    $isBothDirections = strpos($key, '_all') !== false;

                    // Create a unique page ID for this station view
                    $pageId = 'station_' . $stationCode . ($isBothDirections ? '_all' : '');

                    // Add to the collection
                    $popularPages->put($pageId, ($popularPages->get($pageId, 0) + $views));

                    // Store station code for later lookup
                    $stationMap[$pageId] = $stationCode;
                }
            }
        }

        // Sort by views (descending)
        $popularPages = $popularPages->sort(function ($a, $b) {
            return $b <=> $a;
        });

        // Take top 10 and format for display
        return $popularPages->take(10)->map(function ($views, $pageId) use ($stations, $stationMap) {
            $name = $pageId;
            $url = '/';

            // Format page name and URL
            if ($pageId === 'home') {
                $name = 'Home Page';
                $url = '/';
            } elseif ($pageId === 'search') {
                $name = 'Search Page';
                $url = '/search';
            } elseif ($pageId === 'geosearch') {
                $name = 'Geolocation Search';
                $url = '/geosearch';
            } elseif ($pageId === 'not_found') {
                $name = 'Not Found Page';
                $url = '/not-found';
            } elseif (strpos($pageId, 'station_') === 0) {
                $stationCode = $stationMap[$pageId] ?? '';

                // Check if this is a "both directions" view
                $isBothDirections = strpos($pageId, '_all') !== false;

                if (!empty($stationCode) && isset($stations[$stationCode])) {
                    $name = 'Station: ' . $stations[$stationCode]->name;
                    if ($isBothDirections) {
                        $name .= ' (Both Directions)';
                        $url = '/' . $stationCode . '/all';
                    } else {
                        $url = '/' . $stationCode;
                    }
                } else {
                    $name = 'Station: ' . $stationCode;
                    if ($isBothDirections) {
                        $name .= ' (Both Directions)';
                        $url = '/' . $stationCode . '/all';
                    } else {
                        $url = '/' . $stationCode;
                    }
                }
            }

            return [
                'name' => $name,
                'views' => $views,
                'url' => $url
            ];
        });
    }
}
