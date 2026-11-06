<?php

namespace App\Services;

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
 *  - stats_{Y_m_d}_{page}      raw counter per page per day
 *  - stats_agg_day_{Y_m_d}     JSON daily aggregate
 *  - stats_avg_dow_{1-7}       JSON rolling average per ISO weekday
 */
class StatsService
{
    /** Pages counted on their own; station pages are summed separately. */
    private const PAGES = ['home', 'search', 'geosearch'];

    /** Pages listed (besides stations) in the popular pages ranking. */
    private const RANKED_PAGES = ['home', 'search', 'geosearch', 'not_found'];

    private const SCAN_BATCH = 500;

    /**
     * Count a page view. Never throws: a stats outage must not break the site.
     */
    public function trackPageView(string $page): void
    {
        try {
            Redis::incr($this->buildKey(Carbon::now()->format('Y_m_d'), $page));
        } catch (Throwable $e) {
            Log::warning('Failed to track page view: '.$e->getMessage(), ['page' => $page]);
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
        $prefix = $this->buildKey($date, '');
        $views = [];

        foreach ($this->scanKeys($this->buildKey($date, 'station_*')) as $key) {
            $pageId = substr($key, strlen($prefix));
            if (preg_match('/^station_\d+(?:_all)?$/', $pageId)) {
                $views[$pageId] = (int) Redis::get($key);
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
        return (int) Redis::get($this->buildKey($date, $page));
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

    /**
     * All keys matching a pattern, using SCAN so Redis is never blocked.
     * Returned keys have the connection prefix stripped, ready for Redis::get().
     *
     * @return list<string>
     */
    private function scanKeys(string $pattern): array
    {
        $prefix = (string) config('database.redis.options.prefix', '');
        $keys = [];
        $cursor = 0;

        do {
            $result = Redis::scan($cursor, ['match' => $pattern, 'count' => self::SCAN_BATCH]);
            if (! is_array($result)) {
                break;
            }

            [$cursor, $batch] = $result;
            foreach ($batch as $key) {
                $keys[] = $prefix !== '' && str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key;
            }
        } while ((string) $cursor !== '0');

        return array_values(array_unique($keys));
    }

    private function buildKey(string $date, string $page): string
    {
        return "stats_{$date}_{$page}";
    }
}
