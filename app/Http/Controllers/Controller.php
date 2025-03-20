<?php

namespace App\Http\Controllers;

use App\Models\Station;
use App\Services\LppApiService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Facades\View;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    private LppApiService $lppApiService;

    public function __construct(LppApiService $lppApiService)
    {
        $this->lppApiService = $lppApiService;
    }

    private function trackPageView(string $page)
    {
        // Skip if this is an auto-reload
        if (RequestFacade::cookie('autoreload')) {
            return;
        }

        $date = Carbon::now()->format('Y_m_d');
        $key = "stats_{$date}_{$page}";
        Redis::incr($key);
    }

    public function index()
    {
        $this->trackPageView('home');

        return view('search', [
            'stations' => Station::limit(0)->get(),
            'query' => '',
            'direction' => 'all',
            'directionToCenter' => null,
            'hrefs' => [
                'to' => '/search?direction=to',
                'from' => '/search?direction=from',
                'all' => '/search?direction=all'
            ],
            'useLocation' => true,
            'lookingForLocation' => true
        ]);
    }

    public function show(string $stopId)
    {
        $station = Station::where('code', $stopId)->first();

        if (!$station) {
            $this->trackPageView('not_found');
            return view('station-not-found');
        }

        $this->trackPageView('station_' . $stopId);

        // Check if opposite station code exists
        $oppositeCode = $station->oppositeStationCode;
        $hasOppositeStation = !is_null($oppositeCode);

        // Determine which links should be available
        $toHref = null;
        $fromHref = null;

        if ($hasOppositeStation) {
            $toHref = '/' . ($station->is_direction_to_center ? $station->code : $oppositeCode);
            $fromHref = '/' . ($station->is_direction_to_center ? $oppositeCode : $station->code);
        } else {
            // If no opposite station, only enable the current direction button
            if ($station->is_direction_to_center) {
                $toHref = '/' . $station->code;
            } else {
                $fromHref = '/' . $station->code;
            }
        }


        return view('station', [
            'station' => $station,
            'arrivals' => $station->arrivals,
            'directionToCenter' => $station->is_direction_to_center,
            'hrefs' => [
                'to' => $toHref,
                'from' => $fromHref,
                'all' => '/' . $station->code . '/all'
            ]
        ]);
    }

    public function showBothDirections(string $stopId)
    {
        $this->trackPageView('station_' . $stopId . '_all');

        $station = Station::where('code', $stopId)->first();

        if (!$station) {
            return view('station-not-found');
        }

        $arrivals = $station->arrivals;
        $oppositeCode = $station->oppositeStationCode;
        $hasOppositeStation = !is_null($oppositeCode);

        // Only fetch and merge opposite station arrivals if oppositeStationCode exists
        if ($hasOppositeStation) {
            $oppositeStation = Station::where('code', $oppositeCode)->first();
            if ($oppositeStation) {
                $arrivals = collect(array_merge($station->arrivals, $oppositeStation->arrivals))
                    ->sortBy('route_name_numeric')
                    ->values()
                    ->all();
            }
        }

        // Determine which links should be available
        $toHref = null;
        $fromHref = null;

        if ($hasOppositeStation) {
            $toHref = '/' . ($station->is_direction_to_center ? $station->code : $oppositeCode);
            $fromHref = '/' . ($station->is_direction_to_center ? $oppositeCode : $station->code);
        } else {
            // If no opposite station, only enable the current direction button
            if ($station->is_direction_to_center) {
                $toHref = '/' . $station->code;
            } else {
                $fromHref = '/' . $station->code;
            }
        }

        return view('station', [
            'station' => $station,
            'arrivals' => $arrivals,
            'directionToCenter' => null,
            'hrefs' => [
                'to' => $toHref,
                'from' => $fromHref,
                'all' => '/' . $station->code . '/all'
            ]
        ]);
    }

    public function search(Request $request)
    {
        $this->trackPageView('search');

        $query = $request->get('q');
        $direction = $request->get('direction', 'all');

        if (empty($query)) {
            $stations = Station::query();
            if ($direction !== 'all') {
                $stations->where('is_direction_to_center', $direction === 'to');
            }
            $stations = $stations->limit(10)->get();
        } else {
            $stations = Station::where(function ($q) use ($query) {
                $q->where('name', $query) // Exact match
                    ->orWhere('code', 'LIKE', $query . '%') // Code match
                    ->orWhere('name', 'LIKE', $query . '%') // Exact start match
                    ->orWhereRaw('MATCH(name) AGAINST(? IN BOOLEAN MODE)', [$query . '*']) // Full-text match
                    ->orWhere('name', 'LIKE', '%' . $query . '%'); // Partial match
            })
                ->orderByRaw("
                CASE
                    WHEN name = ? THEN 1
                    WHEN code LIKE ? THEN 2
                    WHEN name LIKE ? THEN 3
                    WHEN MATCH(name) AGAINST(? IN BOOLEAN MODE) THEN 4
                    WHEN name LIKE ? THEN 5
                    ELSE 6
                END", [$query, $query . '%', $query . '%', $query . '*', '%' . $query . '%']);


            if ($direction !== 'all') {
                $stations->where('is_direction_to_center', $direction === 'to');
            }

            $stations = $stations->get();

            if ($stations->count() === 1) {
                $station = $stations->first();
                if ($direction === 'all') {
                    return redirect('/' . $station->code . '/all');
                }
                return redirect('/' . $station->code);
            }
        }

        return view('search', [
            'stations' => $stations,
            'query' => $query,
            'direction' => $direction,
            'directionToCenter' => $direction === 'all' ? null : ($direction === 'to' ? true : false),
            'useLocation' => false,
            'hrefs' => [
                'to' => '/search?q=' . $query . '&direction=to',
                'from' => '/search?q=' . $query . '&direction=from',
                'all' => '/search?q=' . $query . '&direction=all'
            ],
            'locationFailed' => $request->has('locationFailed')
        ]);
    }

    public function geosearch(Request $request)
    {
        $this->trackPageView('geosearch');

        $latitude = $request->get('lat');
        $longitude = $request->get('lon');

        if (empty($latitude) || empty($longitude)) {
            return redirect('/search');
        }

        $nearbyStations = $this->lppApiService->getStationsInRange($latitude, $longitude);

        if (!$nearbyStations) {
            return view('search', [
                'stations' => collect([]),
                'query' => '',
                'direction' => 'all',
                'directionToCenter' => null,
                'hrefs' => [
                    'to' => "/geosearch?lat={$latitude}&lon={$longitude}&direction=to",
                    'from' => "/geosearch?lat={$latitude}&lon={$longitude}&direction=from",
                    'all' => "/geosearch?lat={$latitude}&lon={$longitude}&direction=all"
                ],
                'useLocation' => true,
                'error' => 'V bližini ni najdenih postaj.'
            ]);
        }

        // Convert API response to Station models
        $stationCodes = collect($nearbyStations)->pluck('ref_id')->toArray();
        $stations = Station::whereIn('code', $stationCodes)->get();

        // Sort stations in the same order as the API response
        $stationsMap = $stations->keyBy('code');
        $sortedStations = collect($nearbyStations)->map(function ($station) use ($stationsMap) {
            if (isset($stationsMap[$station['ref_id']])) {
                $stationModel = $stationsMap[$station['ref_id']];
                // Inject the distance as a temporary attribute
                $stationModel->distance = round($station['distance']);
                return $stationModel;
            }
            return null;
        })->filter();

        if ($sortedStations->count() === 1) {
            $station = $sortedStations->first();
            return redirect('/' . $station->code . '/all');
        }

        return view('search', [
            'stations' => $sortedStations,
            'query' => '',
            'direction' => 'all',
            'directionToCenter' => null,
            'hrefs' => [
                'to' => "/geosearch?lat={$latitude}&lon={$longitude}&direction=to",
                'from' => "/geosearch?lat={$latitude}&lon={$longitude}&direction=from",
                'all' => "/geosearch?lat={$latitude}&lon={$longitude}&direction=all"
            ],
            'useLocation' => true
        ]);
    }
}
