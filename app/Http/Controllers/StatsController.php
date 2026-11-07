<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatsRequest;
use App\Services\Stats\StatsInsights;
use App\Support\FiltersLiveSince;
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
            'liveSince' => FiltersLiveSince::date()->format('j. n. Y'),
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
                'lookup' => $this->lookup($insights, $term),
            ]);
        } catch (Throwable $e) {
            report($e);

            return view('stats', [...$view, 'unavailable' => true]);
        }
    }

    /**
     * Resolve the term to a station group first, so unknown terms never reach the cache and
     * every spelling of the same group shares one entry.
     *
     * @return array<string, mixed>|null
     */
    private function lookup(StatsInsights $insights, ?string $term): ?array
    {
        $id = $term === null ? null : $insights->resolveGroup($term);
        if ($id === null) {
            return null;
        }

        return Cache::remember('stats_lookup_'.md5($id), self::CACHE_SECONDS, fn (): ?array => $insights->lookupGroup($id));
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
