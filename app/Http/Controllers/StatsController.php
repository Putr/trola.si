<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatsRequest;
use App\Services\Stats\StatsInsights;
use App\Support\StationName;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Throwable;

class StatsController extends Controller
{
    private const CACHE_SECONDS = 600;

    public function index(StatsRequest $request, StatsInsights $insights): View
    {
        $months = $request->months();
        $list = $request->list();
        $term = $request->stationTerm();

        $view = [
            'months' => $months,
            'list' => $list,
            'term' => $term,
            'liveSince' => Carbon::parse((string) config('stats.filters_live_since'))->format('j. n. Y'),
            'unavailable' => false,
        ];

        try {
            $base = Cache::remember('stats_page_v2', self::CACHE_SECONDS, fn (): array => [
                'overview' => $insights->overview(),
                'trend' => $insights->trend(12),
            ]);

            return view('stats', [
                ...$view,
                'overview' => $base['overview'],
                'trend' => $this->sliceTrend($base['trend'], $months),
                'stations' => Cache::remember("stats_list_{$list}", self::CACHE_SECONDS, fn (): array => $insights->stations($list)),
                'lookup' => $term === null ? null : Cache::remember(
                    'stats_lookup_'.md5(StationName::key($term)),
                    self::CACHE_SECONDS,
                    fn (): array => ['result' => $insights->lookup($term)],
                )['result'],
            ]);
        } catch (Throwable $e) {
            report($e);

            return view('stats', [...$view, 'unavailable' => true]);
        }
    }

    /**
     * @param  array{days: list<array{date: string, views: ?int, avg28: ?float, last_year_avg28: ?float}>, live_since: string}  $trend
     * @return array{days: list<array{date: string, views: ?int, avg28: ?float, last_year_avg28: ?float}>, live_since: string}
     */
    private function sliceTrend(array $trend, int $months): array
    {
        $from = Carbon::now()->startOfDay()->subMonthsNoOverflow($months)->format('Y-m-d');

        return [
            'days' => array_values(array_filter($trend['days'], fn (array $day): bool => $day['date'] >= $from)),
            'live_since' => $trend['live_since'],
        ];
    }
}
