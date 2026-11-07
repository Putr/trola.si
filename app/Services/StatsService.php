<?php

namespace App\Services;

use App\Enums\FilterReason;
use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Anonymous page-view counters in Redis, and the aggregates shown on /stats.
 *
 * Key formats are frozen because production history lives in them:
 *  - stats_day_{Y_m_d}         hash per day, field = page id (home, search, geosearch,
 *                              not_found, station_{code}, station_{code}_all), value = views;
 *                              also `app` (views from the Android app) and
 *                              `filtered:{reload|bot|datacenter|foreign}` (dropped requests)
 *  - stats_agg_day_{Y_m_d}     JSON daily aggregate
 *  - stats_avg_dow_{1-7}       JSON rolling average per ISO weekday
 */
class StatsService
{
    private const APP_FIELD = 'app';

    private const FILTERED_PREFIX = 'filtered:';

    /** Pages counted on their own; station pages are summed separately. */
    private const PAGES = ['home', 'search', 'geosearch'];

    /** Pages listed (besides stations) in the popular pages ranking. */
    private const RANKED_PAGES = ['home', 'search', 'geosearch', 'not_found'];

    /**
     * Count a page view, and an app view too when it came from the Android app.
     * Never throws: a stats outage must not break the site.
     */
    public function trackPageView(string $page, bool $fromApp = false): void
    {
        try {
            $key = $this->dayKey(Carbon::now()->format('Y_m_d'));
            Redis::hincrby($key, $page, 1);
            if ($fromApp) {
                Redis::hincrby($key, self::APP_FIELD, 1);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to track page view: '.$e->getMessage(), ['page' => $page]);
        }
    }

    /**
     * Count a request that was dropped from the page views, by reason. Never throws.
     */
    public function trackFiltered(FilterReason $reason): void
    {
        try {
            Redis::hincrby($this->dayKey(Carbon::now()->format('Y_m_d')), self::FILTERED_PREFIX.$reason->value, 1);
        } catch (Throwable $e) {
            Log::warning('Failed to track filtered view: '.$e->getMessage(), ['reason' => $reason->value]);
        }
    }

    /**
     * Raw totals for a day, used when no aggregate exists yet.
     *
     * @param  string  $date  Y_m_d
     * @return array{date: string, home: int, search: int, geosearch: int, stations: int, pct_from_avg: null, yoy: null}
     */
    public function getDailyStats(string $date): array
    {
        return [
            'date' => Carbon::createFromFormat('Y_m_d', $date)->format('Y-m-d'),
            ...$this->totalsForDate($date),
            'pct_from_avg' => null,
            'yoy' => null,
        ];
    }

    /**
     * The most viewed pages over the last $days days, with week-over-week and
     * year-over-year trends.
     *
     * @return Collection<string, array{name: string, url: string, views: int, trends: array{wow?: array{type: string, diff: int, pct: float}, yoy?: array{type: string, diff: int, pct: float}}, change: array{type: string, diff: int, pct: float}|null}>
     */
    public function getPopularPages(int $days = 7, int $limit = 10): Collection
    {
        $current = $this->pageViewsForPeriod($days, 0);
        $previous = $this->pageViewsForPeriod($days, $days);
        $stations = Station::query()->get(['code', 'name'])->keyBy('code');

        return $current
            ->sortDesc()
            ->take($limit)
            ->map(function (int $views, string $pageId) use ($previous, $stations, $days): array {
                $trends = array_filter([
                    'wow' => $this->trend('WoW', $views, $previous->get($pageId, 0)),
                    'yoy' => $this->trend('YOY', $views, $this->pageViewsOnDays($pageId, $days, 365)),
                ]);

                return [
                    ...$this->describePage($pageId, $stations),
                    'views' => $views,
                    'trends' => $trends,
                    'change' => $trends['wow'] ?? null,
                ];
            });
    }

    /**
     * Total station page views on a day (both single- and both-direction pages).
     *
     * @param  string  $date  Y_m_d
     */
    public function getStationViewsForDate(string $date): int
    {
        return array_sum($this->stationViewsForDate($date));
    }

    /**
     * Build and store the aggregate for a day.
     *
     * @param  string  $date  Y_m_d
     * @return array<string, mixed>
     */
    public function aggregateDay(string $date, bool $forceRecalculate = false): array
    {
        if (! $forceRecalculate && ($cached = $this->getAggregatedDay($date)) !== null) {
            return $cached;
        }

        $carbonDate = Carbon::createFromFormat('Y_m_d', $date);
        $totals = $this->totalsForDate($date);
        $average = $this->getJson('stats_avg_dow_'.$carbonDate->dayOfWeekIso);

        $diffFromAvg = [];
        $pctFromAvg = [];
        if ($average !== null) {
            foreach ($totals as $metric => $value) {
                $avg = $average[$metric] ?? 0;
                $diffFromAvg[$metric] = $value - $avg;
                $pctFromAvg[$metric] = $avg > 0 ? round(($value - $avg) / $avg * 100, 1) : ($value > 0 ? 100.0 : 0.0);
            }
        }

        $aggregated = [
            'date' => $carbonDate->format('Y-m-d'),
            ...$totals,
            'app' => $this->getPageViews($date, self::APP_FIELD),
            'filtered' => $this->filteredForDate($date),
            'diff_from_avg' => $diffFromAvg,
            'pct_from_avg' => $pctFromAvg,
            'yoy' => $this->yearOverYear($carbonDate, $totals),
        ];

        Redis::set("stats_agg_day_{$date}", json_encode($aggregated));

        return $aggregated;
    }

    /**
     * @param  string  $date  Y_m_d
     * @return array<string, mixed>|null
     */
    public function getAggregatedDay(string $date): ?array
    {
        return $this->getJson("stats_agg_day_{$date}");
    }

    /**
     * Aggregates for many days in one round trip. Days without an aggregate are null.
     *
     * @param  list<string>  $dates  Y_m_d
     * @return array<string, array<string, mixed>|null> keyed by Y_m_d
     */
    public function getAggregatedDays(array $dates): array
    {
        $result = [];

        foreach (array_chunk($dates, 500) as $chunk) {
            $values = Redis::mget(array_map(fn (string $date): string => "stats_agg_day_{$date}", $chunk));

            foreach ($chunk as $index => $date) {
                $data = is_string($values[$index] ?? null) ? json_decode($values[$index], true) : null;
                $result[$date] = is_array($data) ? $data : null;
            }
        }

        return $result;
    }

    /**
     * Average totals per ISO weekday over the last $months months.
     */
    public function calculateRollingAverages(int $months = 3): void
    {
        $end = Carbon::now()->startOfDay();
        $start = $end->copy()->subMonths($months);

        for ($dayOfWeek = 1; $dayOfWeek <= 7; $dayOfWeek++) {
            $sums = array_fill_keys([...self::PAGES, 'stations'], 0);
            $count = 0;

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if ($date->dayOfWeekIso !== $dayOfWeek) {
                    continue;
                }

                foreach ($this->totalsForDate($date->format('Y_m_d')) as $metric => $value) {
                    $sums[$metric] += $value;
                }
                $count++;
            }

            if ($count === 0) {
                continue;
            }

            Redis::set("stats_avg_dow_{$dayOfWeek}", json_encode([
                ...array_map(fn (int $sum): float => round($sum / $count, 2), $sums),
                'last_updated' => Carbon::now()->toIso8601String(),
            ]));
        }
    }

    /**
     * @return array{home: int, search: int, geosearch: int, stations: int}
     */
    private function totalsForDate(string $date): array
    {
        $totals = [];
        foreach (self::PAGES as $page) {
            $totals[$page] = $this->getPageViews($date, $page);
        }
        $totals['stations'] = $this->getStationViewsForDate($date);

        return $totals;
    }

    /**
     * @param  string  $date  Y_m_d
     * @return array{reload: int, bot: int, datacenter: int, foreign: int}
     */
    private function filteredForDate(string $date): array
    {
        return [
            'reload' => $this->getPageViews($date, self::FILTERED_PREFIX.FilterReason::Reload->value),
            'bot' => $this->getPageViews($date, self::FILTERED_PREFIX.FilterReason::Bot->value),
            'datacenter' => $this->getPageViews($date, self::FILTERED_PREFIX.FilterReason::Datacenter->value),
            'foreign' => $this->getPageViews($date, self::FILTERED_PREFIX.FilterReason::Foreign->value),
        ];
    }

    /**
     * @param  array<string, int>  $totals
     * @return array<string, array{diff: int, pct: float}>
     */
    private function yearOverYear(Carbon $date, array $totals): array
    {
        $yearAgo = $this->totalsForDate($date->copy()->subYear()->format('Y_m_d'));

        if (array_sum($yearAgo) === 0) {
            return [];
        }

        $yoy = [];
        foreach ($totals as $metric => $value) {
            $previous = $yearAgo[$metric];
            $yoy[$metric] = $previous === 0
                ? ['diff' => $value, 'pct' => $value > 0 ? 100.0 : 0.0]
                : ['diff' => $value - $previous, 'pct' => round(($value - $previous) / $previous * 100, 2)];
        }

        return $yoy;
    }

    /**
     * Views per page id over $days days, ending $offsetDays days ago.
     *
     * @return Collection<string, int>
     */
    private function pageViewsForPeriod(int $days, int $offsetDays): Collection
    {
        $views = collect();

        for ($i = $offsetDays; $i < $offsetDays + $days; $i++) {
            $date = Carbon::now()->subDays($i)->format('Y_m_d');

            foreach (self::RANKED_PAGES as $page) {
                $count = $this->getPageViews($date, $page);
                if ($count > 0) {
                    $views->put($page, $views->get($page, 0) + $count);
                }
            }

            foreach ($this->stationViewsForDate($date) as $pageId => $count) {
                if ($count > 0) {
                    $views->put($pageId, $views->get($pageId, 0) + $count);
                }
            }
        }

        return $views;
    }

    /**
     * Views of one page id over $days days, starting $daysAgo days ago.
     */
    private function pageViewsOnDays(string $pageId, int $days, int $daysAgo): int
    {
        $total = 0;
        for ($i = 0; $i < $days; $i++) {
            $total += $this->getPageViews(Carbon::now()->subDays($daysAgo + $i)->format('Y_m_d'), $pageId);
        }

        return $total;
    }

    /**
     * Station page views on a day, keyed by page id (station_{code} or station_{code}_all).
     *
     * @return array<string, int>
     */
    private function stationViewsForDate(string $date): array
    {
        $counts = Redis::hgetall($this->dayKey($date));
        $views = [];

        foreach (is_array($counts) ? $counts : [] as $pageId => $count) {
            if (preg_match('/^station_\d+(?:_all)?$/', (string) $pageId)) {
                $views[(string) $pageId] = (int) $count;
            }
        }

        return $views;
    }

    /**
     * @param  Collection<string, Station>  $stations
     * @return array{name: string, url: string}
     */
    private function describePage(string $pageId, Collection $stations): array
    {
        $fixed = [
            'home' => ['name' => 'Home Page', 'url' => '/'],
            'search' => ['name' => 'Search Page', 'url' => '/search'],
            'geosearch' => ['name' => 'Geolocation Search', 'url' => '/geosearch'],
            'not_found' => ['name' => 'Not Found Page', 'url' => '/not-found'],
        ];

        if (isset($fixed[$pageId])) {
            return $fixed[$pageId];
        }

        preg_match('/^station_(\d+)(_all)?$/', $pageId, $matches);
        $code = $matches[1] ?? '';
        $bothDirections = isset($matches[2]);

        return [
            'name' => 'Station: '.($stations->get($code)->name ?? $code).($bothDirections ? ' (Both Directions)' : ''),
            'url' => '/'.$code.($bothDirections ? '/all' : ''),
        ];
    }

    /**
     * @return array{type: string, diff: int, pct: float}|null
     */
    private function trend(string $type, int $views, int $previous): ?array
    {
        if ($previous > 0) {
            return ['type' => $type, 'diff' => $views - $previous, 'pct' => round(($views - $previous) / $previous * 100, 1)];
        }

        return $views > 0 ? ['type' => $type, 'diff' => $views, 'pct' => 100.0] : null;
    }

    private function getPageViews(string $date, string $page): int
    {
        return (int) Redis::hget($this->dayKey($date), $page);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getJson(string $key): ?array
    {
        $value = Redis::get($key);
        $data = is_string($value) ? json_decode($value, true) : null;

        return is_array($data) ? $data : null;
    }

    private function dayKey(string $date): string
    {
        return "stats_day_{$date}";
    }
}
