<?php

namespace App\Http\Controllers;

use App\Models\Station;
use App\Repositories\StationRepository;
use App\Services\ArrivalsService;
use App\Services\PageViewTracker;
use Illuminate\Http\Response;

class StationController extends Controller
{
    public function __construct(
        private readonly StationRepository $stations,
        private readonly ArrivalsService $arrivals,
        private readonly PageViewTracker $tracker,
    ) {}

    /**
     * Arrivals at one station (one direction).
     */
    public function show(string $code): Response
    {
        $station = $this->stations->findByCode($code);
        if ($station === null) {
            return $this->notFound();
        }

        $this->tracker->track("station_{$code}");

        return response()->view('station', [
            'station' => $station,
            'arrivals' => $this->arrivals->forStation($station),
            'directionToCenter' => $station->is_direction_to_center,
            'hrefs' => $this->directionLinks($station, $this->opposite($station)),
        ]);
    }

    /**
     * Arrivals at a station and its opposite station, merged.
     */
    public function showBothDirections(string $code): Response
    {
        $station = $this->stations->findByCode($code);
        if ($station === null) {
            return $this->notFound();
        }

        $this->tracker->track("station_{$code}_all");

        $opposite = $this->opposite($station);
        $arrivals = $this->arrivals->forStation($station);

        if ($opposite !== null) {
            $arrivals = collect([...$arrivals, ...$this->arrivals->forStation($opposite)])
                ->sortBy('route_name_numeric')
                ->values()
                ->all();
        }

        return response()->view('station', [
            'station' => $station,
            'arrivals' => $arrivals,
            'directionToCenter' => null,
            'hrefs' => $this->directionLinks($station, $opposite),
        ]);
    }

    private function opposite(Station $station): ?Station
    {
        return $station->opposite_station_id !== null
            ? $this->stations->findById($station->opposite_station_id)
            : null;
    }

    /**
     * Links for the "to centre / both / from centre" buttons. A direction with no
     * station (no known opposite) gets no link.
     *
     * @return array{to: ?string, from: ?string, all: string}
     */
    private function directionLinks(Station $station, ?Station $opposite): array
    {
        $toCenter = $station->is_direction_to_center ? $station : $opposite;
        $fromCenter = $station->is_direction_to_center ? $opposite : $station;

        return [
            'to' => $toCenter ? route('station', ['code' => $toCenter->code], false) : null,
            'from' => $fromCenter ? route('station', ['code' => $fromCenter->code], false) : null,
            'all' => route('station.both-directions', ['code' => $station->code], false),
        ];
    }

    private function notFound(): Response
    {
        $this->tracker->track('not_found');

        return response()->view('station-not-found', status: 404);
    }
}
