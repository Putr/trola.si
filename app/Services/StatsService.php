<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class StatsService
{
    /**
     * Track a page view
     *
     * @param  string  $page  Page identifier (e.g., 'home', 'search', 'station_123')
     */
    public function trackPageView(string $page): void
    {
        try {
            $date = Carbon::now()->format('Y_m_d');
            $key = $this->buildKey($date, $page);
            Redis::incr($key);
        } catch (\Exception $e) {
            Log::error('Failed to track page view: '.$e->getMessage(), [
                'page' => $page,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Get daily stats for a specific date
     *
     * @param  string  $date  Date in Y_m_d format
     */
    public function getDailyStats(string $date): array
    {
        try {
            $homeViews = $this->getPageViews($date, 'home');
            $searchViews = $this->getPageViews($date, 'search');
            $geosearchViews = $this->getPageViews($date, 'geosearch');
            $stationViews = $this->getStationViewsForDate($date);

            return [
                'date' => Carbon::createFromFormat('Y_m_d', $date)->format('Y-m-d'),
                'home' => $homeViews,
                'search' => $searchViews,
                'geosearch' => $geosearchViews,
                'stations' => $stationViews,
                'pct_from_avg' => null,
                'yoy' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get daily stats: '.$e->getMessage(), [
                'date' => $date,
                'exception' => $e,
            ]);

            return [
                'date' => Carbon::createFromFormat('Y_m_d', $date)->format('Y-m-d'),
                'home' => 0,
                'search' => 0,
                'geosearch' => 0,
                'stations' => 0,
                'pct_from_avg' => null,
                'yoy' => null,
            ];
        }
    }

    /**
     * Get popular pages from the last N days
     *
     * @param  int  $days  Number of days to look back (default: 7)
     * @param  int  $limit  Number of top pages to return (default: 10)
     */
    public function getPopularPages(int $days = 7, int $limit = 10): Collection
    {
        $popularPages = collect();
        $previousWeekPages = collect();
        $stationMap = [];

        try {
            // Get all station data for lookup
            $stations = \App\Models\Station::all()->keyBy('code');

            // Get data for the specified number of days (current week)
            for ($i = 0; $i < $days; $i++) {
                $date = Carbon::now()->subDays($i)->format('Y_m_d');

                // Process standard page views
                $this->aggregatePageViews($popularPages, $date, 'home');
                $this->aggregatePageViews($popularPages, $date, 'search');
                $this->aggregatePageViews($popularPages, $date, 'geosearch');
                $this->aggregatePageViews($popularPages, $date, 'not_found');

                // Process station page views using SCAN
                $this->aggregateStationViews($popularPages, $stationMap, $date);
            }

            // Get data for previous week (for comparison)
            for ($i = $days; $i < $days * 2; $i++) {
                $date = Carbon::now()->subDays($i)->format('Y_m_d');

                // Process standard page views
                $this->aggregatePageViews($previousWeekPages, $date, 'home');
                $this->aggregatePageViews($previousWeekPages, $date, 'search');
                $this->aggregatePageViews($previousWeekPages, $date, 'geosearch');
                $this->aggregatePageViews($previousWeekPages, $date, 'not_found');

                // Process station page views
                $this->aggregateStationViews($previousWeekPages, $stationMap, $date);
            }

            // Sort by views (descending)
            $popularPages = $popularPages->sort(function ($a, $b) {
                return $b <=> $a;
            });

            // Take top N and format for display with all available trend data
            return $popularPages->take($limit)->map(function ($views, $pageId) use ($stations, $stationMap, $previousWeekPages, $days) {
                $pageData = $this->formatPageForDisplay($pageId, $views, $stations, $stationMap);

                $trends = [];

                // Calculate week-over-week (WoW) change
                $previousViews = $previousWeekPages->get($pageId, 0);
                if ($previousViews > 0) {
                    $diff = $views - $previousViews;
                    $pct = round(($diff / $previousViews) * 100, 1);
                    $trends['wow'] = [
                        'type' => 'WoW',
                        'diff' => $diff,
                        'pct' => $pct,
                    ];
                } elseif ($views > 0 && $previousViews === 0) {
                    // New page or increased from 0
                    $trends['wow'] = [
                        'type' => 'WoW',
                        'diff' => $views,
                        'pct' => 100.0,
                    ];
                }

                // Calculate year-over-year (YOY) change
                $yearAgoViews = $this->getPageViewsForPeriod($pageId, $days, 365);
                if ($yearAgoViews > 0) {
                    $diff = $views - $yearAgoViews;
                    $pct = round(($diff / $yearAgoViews) * 100, 1);
                    $trends['yoy'] = [
                        'type' => 'YOY',
                        'diff' => $diff,
                        'pct' => $pct,
                    ];
                } elseif ($views > 0 && $yearAgoViews === 0) {
                    $trends['yoy'] = [
                        'type' => 'YOY',
                        'diff' => $views,
                        'pct' => 100.0,
                    ];
                }

                $pageData['trends'] = $trends;
                // Keep 'change' for backward compatibility (WoW)
                $pageData['change'] = $trends['wow'] ?? null;

                return $pageData;
            });
        } catch (\Exception $e) {
            Log::error('Failed to get popular pages: '.$e->getMessage(), [
                'days' => $days,
                'limit' => $limit,
                'exception' => $e,
            ]);

            return collect([]);
        }
    }

    /**
     * Get total station views for a specific date
     *
     * @param  string  $date  Date in Y_m_d format
     */
    public function getStationViewsForDate(string $date): int
    {
        try {
            $totalViews = 0;
            $pattern = $this->buildKey($date, 'station_*');

            // Use KEYS (original approach - faster for this use case)
            $stationKeys = Redis::keys($pattern);

            foreach ($stationKeys as $key) {
                // Remove prefix if present (Laravel Redis returns keys with prefix)
                $cleanKey = $this->removePrefix($key);
                $views = (int) Redis::get($cleanKey);
                $totalViews += $views;
            }

            return $totalViews;
        } catch (\Exception $e) {
            Log::error('Failed to get station views for date: '.$e->getMessage(), [
                'date' => $date,
                'exception' => $e,
            ]);

            return 0;
        }
    }

    /**
     * Get total page views for a specific page over a period starting N days ago
     *
     * @param  string  $pageId  Page identifier
     * @param  int  $days  Number of days in the period
     * @param  int  $daysAgo  How many days ago to start the period
     */
    private function getPageViewsForPeriod(string $pageId, int $days, int $daysAgo): int
    {
        $totalViews = 0;
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($daysAgo + $i)->format('Y_m_d');
            $totalViews += $this->getPageViews($date, $pageId);
        }

        return $totalViews;
    }

    /**
     * Get page views for a specific page on a specific date
     *
     * @param  string  $date  Date in Y_m_d format
     * @param  string  $page  Page identifier
     */
    private function getPageViews(string $date, string $page): int
    {
        try {
            $key = $this->buildKey($date, $page);
            $value = Redis::get($key);

            return $value ? (int) $value : 0;
        } catch (\Exception $e) {
            Log::error('Failed to get page views: '.$e->getMessage(), [
                'date' => $date,
                'page' => $page,
                'exception' => $e,
            ]);

            return 0;
        }
    }

    /**
     * Aggregate page views for a specific page type
     */
    private function aggregatePageViews(Collection $popularPages, string $date, string $page): void
    {
        $views = $this->getPageViews($date, $page);
        if ($views > 0) {
            $popularPages->put($page, ($popularPages->get($page, 0) + $views));
        }
    }

    /**
     * Aggregate station views for a specific date using SCAN
     */
    private function aggregateStationViews(Collection $popularPages, array &$stationMap, string $date): void
    {
        try {
            $pattern = $this->buildKey($date, 'station_*');

            // Use KEYS (original approach - faster for this use case)
            $stationKeys = Redis::keys($pattern);

            foreach ($stationKeys as $redisKey) {
                // Remove prefix if present
                $key = $this->removePrefix($redisKey);
                $views = (int) Redis::get($key);

                if ($views <= 0) {
                    continue;
                }

                // Use regex to extract station code and check if it's a "both directions" view
                if (preg_match('/^stats_[0-9_]+_station_([0-9]+)(?:_all)?$/', $key, $matches)) {
                    $stationCode = $matches[1];
                    $isBothDirections = strpos($key, '_all') !== false;

                    // Create a unique page ID for this station view
                    $pageId = 'station_'.$stationCode.($isBothDirections ? '_all' : '');

                    // Add to the collection
                    $popularPages->put($pageId, ($popularPages->get($pageId, 0) + $views));

                    // Store station code for later lookup
                    $stationMap[$pageId] = $stationCode;
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to aggregate station views: '.$e->getMessage(), [
                'date' => $date,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Format page data for display
     */
    private function formatPageForDisplay(string $pageId, int $views, Collection $stations, array $stationMap): array
    {
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
            $isBothDirections = strpos($pageId, '_all') !== false;

            if (! empty($stationCode) && isset($stations[$stationCode])) {
                $name = 'Station: '.$stations[$stationCode]->name;
                if ($isBothDirections) {
                    $name .= ' (Both Directions)';
                    $url = '/'.$stationCode.'/all';
                } else {
                    $url = '/'.$stationCode;
                }
            } else {
                $name = 'Station: '.$stationCode;
                if ($isBothDirections) {
                    $name .= ' (Both Directions)';
                    $url = '/'.$stationCode.'/all';
                } else {
                    $url = '/'.$stationCode;
                }
            }
        }

        return [
            'name' => $name,
            'views' => $views,
            'url' => $url,
        ];
    }

    /**
     * Aggregate and cache daily stats for a specific date
     *
     * @param  string  $date  Date in Y_m_d format
     */
    public function aggregateDay(string $date, bool $forceRecalculate = false): array
    {
        try {
            // Check if already cached (unless forcing recalculation)
            if (! $forceRecalculate) {
                $cached = $this->getAggregatedDay($date);
                if ($cached !== null) {
                    return $cached;
                }
            }

            // Get basic stats
            $homeViews = $this->getPageViews($date, 'home');
            $searchViews = $this->getPageViews($date, 'search');
            $geosearchViews = $this->getPageViews($date, 'geosearch');
            $stationViews = $this->getStationViewsForDate($date);

            $carbonDate = Carbon::createFromFormat('Y_m_d', $date);
            $dayOfWeek = $carbonDate->dayOfWeekIso; // 1 (Monday) to 7 (Sunday)

            // Get rolling average for this day of week
            $rollingAvg = $this->getRollingAverageForDayOfWeek($dayOfWeek);

            // Calculate difference and percentage from rolling average
            $diffFromAvg = [];
            $pctFromAvg = [];
            if ($rollingAvg) {
                $rollingHome = $rollingAvg['home'] ?? 0;
                $rollingSearch = $rollingAvg['search'] ?? 0;
                $rollingGeosearch = $rollingAvg['geosearch'] ?? 0;
                $rollingStations = $rollingAvg['stations'] ?? 0;

                $diffFromAvg = [
                    'home' => $homeViews - $rollingHome,
                    'search' => $searchViews - $rollingSearch,
                    'geosearch' => $geosearchViews - $rollingGeosearch,
                    'stations' => $stationViews - $rollingStations,
                ];

                // Calculate percentage from rolling average
                $pctFromAvg = [
                    'home' => $rollingHome > 0 ? round((($homeViews - $rollingHome) / $rollingHome) * 100, 1) : ($homeViews > 0 ? 100.0 : 0.0),
                    'search' => $rollingSearch > 0 ? round((($searchViews - $rollingSearch) / $rollingSearch) * 100, 1) : ($searchViews > 0 ? 100.0 : 0.0),
                    'geosearch' => $rollingGeosearch > 0 ? round((($geosearchViews - $rollingGeosearch) / $rollingGeosearch) * 100, 1) : ($geosearchViews > 0 ? 100.0 : 0.0),
                    'stations' => $rollingStations > 0 ? round((($stationViews - $rollingStations) / $rollingStations) * 100, 1) : ($stationViews > 0 ? 100.0 : 0.0),
                ];
            }

            // Get year-over-year comparison
            $yoy = $this->calculateYearOverYearForDay($date, [
                'home' => $homeViews,
                'search' => $searchViews,
                'geosearch' => $geosearchViews,
                'stations' => $stationViews,
            ]);

            $aggregated = [
                'date' => $carbonDate->format('Y-m-d'),
                'home' => $homeViews,
                'search' => $searchViews,
                'geosearch' => $geosearchViews,
                'stations' => $stationViews,
                'diff_from_avg' => $diffFromAvg,
                'pct_from_avg' => $pctFromAvg,
                'yoy' => $yoy,
            ];

            // Cache the aggregated data
            $this->setAggregatedData('day', $date, $aggregated);

            return $aggregated;
        } catch (\Exception $e) {
            Log::error('Failed to aggregate day: '.$e->getMessage(), [
                'date' => $date,
                'exception' => $e,
            ]);

            return [];
        }
    }

    /**
     * Get cached daily aggregate
     *
     * @param  string  $date  Date in Y_m_d format
     */
    public function getAggregatedDay(string $date): ?array
    {
        return $this->getAggregatedData('day', $date);
    }

    /**
     * Aggregate weekly stats for a specific week
     *
     * @param  string  $year  Year (e.g., '2024')
     * @param  int  $week  ISO week number (1-53)
     */
    public function aggregateWeek(string $year, int $week): array
    {
        try {
            // Check if already cached
            $cached = $this->getAggregatedWeek($year, $week);
            if ($cached !== null) {
                return $cached;
            }

            // Get the first day of the week (Monday) for ISO week
            // ISO week: year starts on the Monday of the week containing Jan 4
            $firstDay = Carbon::now()->setISODate((int) $year, $week)->startOfWeek();

            // Aggregate all days in the week
            $homeViews = 0;
            $searchViews = 0;
            $geosearchViews = 0;
            $stationViews = 0;

            for ($i = 0; $i < 7; $i++) {
                $day = $firstDay->copy()->addDays($i);
                $dateKey = $day->format('Y_m_d');

                $homeViews += $this->getPageViews($dateKey, 'home');
                $searchViews += $this->getPageViews($dateKey, 'search');
                $geosearchViews += $this->getPageViews($dateKey, 'geosearch');
                $stationViews += $this->getStationViewsForDate($dateKey);
            }

            // Get year-over-year comparison
            $yoy = $this->calculateYearOverYearForWeek($year, $week, [
                'home' => $homeViews,
                'search' => $searchViews,
                'geosearch' => $geosearchViews,
                'stations' => $stationViews,
            ]);

            $aggregated = [
                'year' => $year,
                'week' => $week,
                'start_date' => $firstDay->format('Y-m-d'),
                'end_date' => $firstDay->copy()->endOfWeek()->format('Y-m-d'),
                'home' => $homeViews,
                'search' => $searchViews,
                'geosearch' => $geosearchViews,
                'stations' => $stationViews,
                'yoy' => $yoy,
            ];

            // Cache the aggregated data
            $this->setAggregatedData('week', "{$year}_{$week}", $aggregated);

            return $aggregated;
        } catch (\Exception $e) {
            Log::error('Failed to aggregate week: '.$e->getMessage(), [
                'year' => $year,
                'week' => $week,
                'exception' => $e,
            ]);

            return [];
        }
    }

    /**
     * Get cached weekly aggregate
     *
     * @param  string  $year  Year (e.g., '2024')
     * @param  int  $week  ISO week number (1-53)
     */
    public function getAggregatedWeek(string $year, int $week): ?array
    {
        return $this->getAggregatedData('week', "{$year}_{$week}");
    }

    /**
     * Aggregate monthly stats for a specific month
     *
     * @param  string  $year  Year (e.g., '2024')
     * @param  int  $month  Month (1-12)
     */
    public function aggregateMonth(string $year, int $month): array
    {
        try {
            // Check if already cached
            $cached = $this->getAggregatedMonth($year, $month);
            if ($cached !== null) {
                return $cached;
            }

            // Get the first and last day of the month
            $firstDay = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $lastDay = $firstDay->copy()->endOfMonth();

            // Aggregate all days in the month
            $homeViews = 0;
            $searchViews = 0;
            $geosearchViews = 0;
            $stationViews = 0;

            $currentDay = $firstDay->copy();
            while ($currentDay->lte($lastDay)) {
                $dateKey = $currentDay->format('Y_m_d');

                $homeViews += $this->getPageViews($dateKey, 'home');
                $searchViews += $this->getPageViews($dateKey, 'search');
                $geosearchViews += $this->getPageViews($dateKey, 'geosearch');
                $stationViews += $this->getStationViewsForDate($dateKey);

                $currentDay->addDay();
            }

            // Get year-over-year comparison
            $yoy = $this->calculateYearOverYearForMonth($year, $month, [
                'home' => $homeViews,
                'search' => $searchViews,
                'geosearch' => $geosearchViews,
                'stations' => $stationViews,
            ]);

            $aggregated = [
                'year' => $year,
                'month' => $month,
                'start_date' => $firstDay->format('Y-m-d'),
                'end_date' => $lastDay->format('Y-m-d'),
                'home' => $homeViews,
                'search' => $searchViews,
                'geosearch' => $geosearchViews,
                'stations' => $stationViews,
                'yoy' => $yoy,
            ];

            // Cache the aggregated data
            $this->setAggregatedData('month', "{$year}_{$month}", $aggregated);

            return $aggregated;
        } catch (\Exception $e) {
            Log::error('Failed to aggregate month: '.$e->getMessage(), [
                'year' => $year,
                'month' => $month,
                'exception' => $e,
            ]);

            return [];
        }
    }

    /**
     * Get cached monthly aggregate
     *
     * @param  string  $year  Year (e.g., '2024')
     * @param  int  $month  Month (1-12)
     */
    public function getAggregatedMonth(string $year, int $month): ?array
    {
        return $this->getAggregatedData('month', "{$year}_{$month}");
    }

    /**
     * Build a Redis key for aggregated stats
     *
     * @param  string  $type  Type of aggregation (day, week, month)
     * @param  string  $identifier  Identifier (date, year_week, year_month)
     */
    private function buildAggregateKey(string $type, string $identifier): string
    {
        return "stats_agg_{$type}_{$identifier}";
    }

    /**
     * Store aggregated data in Redis
     *
     * @param  string  $type  Type of aggregation (day, week, month)
     * @param  string  $identifier  Identifier (date, year_week, year_month)
     * @param  array  $data  Aggregated data
     */
    private function setAggregatedData(string $type, string $identifier, array $data): void
    {
        try {
            $key = $this->buildAggregateKey($type, $identifier);
            Redis::set($key, json_encode($data));
        } catch (\Exception $e) {
            Log::error('Failed to set aggregated data: '.$e->getMessage(), [
                'type' => $type,
                'identifier' => $identifier,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Get aggregated data from Redis
     *
     * @param  string  $type  Type of aggregation (day, week, month)
     * @param  string  $identifier  Identifier (date, year_week, year_month)
     */
    private function getAggregatedData(string $type, string $identifier): ?array
    {
        try {
            $key = $this->buildAggregateKey($type, $identifier);
            $value = Redis::get($key);

            if ($value === null) {
                return null;
            }

            $data = json_decode($value, true);

            return is_array($data) ? $data : null;
        } catch (\Exception $e) {
            Log::error('Failed to get aggregated data: '.$e->getMessage(), [
                'type' => $type,
                'identifier' => $identifier,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Build a Redis key for stats
     *
     * @param  string  $date  Date in Y_m_d format
     * @param  string  $page  Page identifier
     */
    private function buildKey(string $date, string $page): string
    {
        return "stats_{$date}_{$page}";
    }

    /**
     * Calculate rolling averages for each day of week from last N months
     *
     * @param  int  $months  Number of months to look back (default: 3)
     */
    public function calculateRollingAverages(int $months = 3): void
    {
        try {
            $endDate = Carbon::now()->startOfDay();
            $startDate = $endDate->copy()->subMonths($months)->startOfDay();

            // For each day of week (1-7, Monday-Sunday)
            for ($dayOfWeek = 1; $dayOfWeek <= 7; $dayOfWeek++) {
                $homeTotal = 0;
                $searchTotal = 0;
                $geosearchTotal = 0;
                $stationsTotal = 0;
                $count = 0;

                // Find all dates that match this day of week within the period
                $currentDate = $startDate->copy();
                while ($currentDate->lte($endDate)) {
                    // Check if this date matches the day of week
                    if ($currentDate->dayOfWeekIso === $dayOfWeek) {
                        $dateKey = $currentDate->format('Y_m_d');

                        $homeTotal += $this->getPageViews($dateKey, 'home');
                        $searchTotal += $this->getPageViews($dateKey, 'search');
                        $geosearchTotal += $this->getPageViews($dateKey, 'geosearch');
                        $stationsTotal += $this->getStationViewsForDate($dateKey);
                        $count++;
                    }

                    $currentDate->addDay();
                }

                if ($count > 0) {
                    $average = [
                        'home' => round($homeTotal / $count, 2),
                        'search' => round($searchTotal / $count, 2),
                        'geosearch' => round($geosearchTotal / $count, 2),
                        'stations' => round($stationsTotal / $count, 2),
                        'last_updated' => Carbon::now()->toIso8601String(),
                    ];

                    // Store rolling average
                    $key = "stats_avg_dow_{$dayOfWeek}";
                    Redis::set($key, json_encode($average));
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to calculate rolling averages: '.$e->getMessage(), [
                'months' => $months,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Get rolling average for a specific day of week
     *
     * @param  int  $dayOfWeek  Day of week (1-7, Monday-Sunday)
     */
    private function getRollingAverageForDayOfWeek(int $dayOfWeek): ?array
    {
        try {
            $key = "stats_avg_dow_{$dayOfWeek}";
            $value = Redis::get($key);

            if ($value === null) {
                return null;
            }

            $data = json_decode($value, true);

            return is_array($data) ? $data : null;
        } catch (\Exception $e) {
            Log::error('Failed to get rolling average for day of week: '.$e->getMessage(), [
                'dayOfWeek' => $dayOfWeek,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Calculate year-over-year comparison for a specific day
     *
     * @param  string  $date  Date in Y_m_d format
     * @param  array  $currentStats  Current stats
     */
    private function calculateYearOverYearForDay(string $date, array $currentStats): array
    {
        try {
            $carbonDate = Carbon::createFromFormat('Y_m_d', $date);
            $yearAgoDate = $carbonDate->copy()->subYear();
            $yearAgoDateKey = $yearAgoDate->format('Y_m_d');

            // Check if data exists from 1 year ago
            $yearAgoHome = $this->getPageViews($yearAgoDateKey, 'home');
            $yearAgoSearch = $this->getPageViews($yearAgoDateKey, 'search');
            $yearAgoGeosearch = $this->getPageViews($yearAgoDateKey, 'geosearch');
            $yearAgoStations = $this->getStationViewsForDate($yearAgoDateKey);

            // If no data from year ago, return empty
            if ($yearAgoHome === 0 && $yearAgoSearch === 0 && $yearAgoGeosearch === 0 && $yearAgoStations === 0) {
                return [];
            }

            return [
                'home' => $this->calculateYoYDiff($currentStats['home'], $yearAgoHome),
                'search' => $this->calculateYoYDiff($currentStats['search'], $yearAgoSearch),
                'geosearch' => $this->calculateYoYDiff($currentStats['geosearch'], $yearAgoGeosearch),
                'stations' => $this->calculateYoYDiff($currentStats['stations'], $yearAgoStations),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to calculate year-over-year for day: '.$e->getMessage(), [
                'date' => $date,
                'exception' => $e,
            ]);

            return [];
        }
    }

    /**
     * Calculate year-over-year comparison for a specific week
     *
     * @param  string  $year  Year (e.g., '2024')
     * @param  int  $week  ISO week number (1-53)
     * @param  array  $currentStats  Current stats
     */
    private function calculateYearOverYearForWeek(string $year, int $week, array $currentStats): array
    {
        try {
            $yearAgo = (int) $year - 1;
            $yearAgoWeek = $this->getAggregatedWeek((string) $yearAgo, $week);

            if ($yearAgoWeek === null || empty($yearAgoWeek)) {
                return [];
            }

            return [
                'home' => $this->calculateYoYDiff($currentStats['home'], $yearAgoWeek['home'] ?? 0),
                'search' => $this->calculateYoYDiff($currentStats['search'], $yearAgoWeek['search'] ?? 0),
                'geosearch' => $this->calculateYoYDiff($currentStats['geosearch'], $yearAgoWeek['geosearch'] ?? 0),
                'stations' => $this->calculateYoYDiff($currentStats['stations'], $yearAgoWeek['stations'] ?? 0),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to calculate year-over-year for week: '.$e->getMessage(), [
                'year' => $year,
                'week' => $week,
                'exception' => $e,
            ]);

            return [];
        }
    }

    /**
     * Calculate year-over-year comparison for a specific month
     *
     * @param  string  $year  Year (e.g., '2024')
     * @param  int  $month  Month (1-12)
     * @param  array  $currentStats  Current stats
     */
    private function calculateYearOverYearForMonth(string $year, int $month, array $currentStats): array
    {
        try {
            $yearAgo = (int) $year - 1;
            $yearAgoMonth = $this->getAggregatedMonth((string) $yearAgo, $month);

            if ($yearAgoMonth === null || empty($yearAgoMonth)) {
                return [];
            }

            return [
                'home' => $this->calculateYoYDiff($currentStats['home'], $yearAgoMonth['home'] ?? 0),
                'search' => $this->calculateYoYDiff($currentStats['search'], $yearAgoMonth['search'] ?? 0),
                'geosearch' => $this->calculateYoYDiff($currentStats['geosearch'], $yearAgoMonth['geosearch'] ?? 0),
                'stations' => $this->calculateYoYDiff($currentStats['stations'], $yearAgoMonth['stations'] ?? 0),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to calculate year-over-year for month: '.$e->getMessage(), [
                'year' => $year,
                'month' => $month,
                'exception' => $e,
            ]);

            return [];
        }
    }

    /**
     * Calculate year-over-year difference and percentage
     *
     * @param  int  $current  Current value
     * @param  int  $previous  Previous year value
     */
    private function calculateYoYDiff(int $current, int $previous): array
    {
        if ($previous === 0) {
            return ['diff' => $current, 'pct' => $current > 0 ? 100.0 : 0.0];
        }

        $diff = $current - $previous;
        $pct = round(($diff / $previous) * 100, 2);

        return [
            'diff' => $diff,
            'pct' => $pct,
        ];
    }

    /**
     * Calculate year-over-year comparisons for all periods
     */
    public function calculateYearOverYear(): void
    {
        try {
            // This method will be called by the command to recalculate YoY for existing aggregates
            // For now, it's handled automatically when aggregating new periods
            // Could be extended to recalculate all existing aggregates if needed
        } catch (\Exception $e) {
            Log::error('Failed to calculate year-over-year: '.$e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Remove Redis prefix from a key if present
     */
    private function removePrefix(string $key): string
    {
        // Get the configured prefix from Redis options
        $prefix = config('database.redis.options.prefix', '');

        // Remove prefix if it exists at the start of the key
        if ($prefix && strpos($key, $prefix) === 0) {
            return substr($key, strlen($prefix));
        }

        return $key;
    }
}
