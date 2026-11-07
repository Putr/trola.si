<?php

namespace App\Services\Stats;

use App\Models\Station;
use App\Services\StatsService;
use App\Support\StationName;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

/**
 * The maths behind /stats. Windows end yesterday, because today is only partly counted.
 *
 * Totals and the trend come from the daily aggregates; station tables and lookups read the
 * per-day hashes directly by key (pipelined, never SCAN).
 *
 * @phpstan-type Comparison array{avg: ?float, vs_prev: ?float, vs_year: ?float, straddles: bool}
 * @phpstan-type Group array{name: string, codes: list<string>, toCenter: array<string, ?bool>}
 */
class StatsInsights
{
    private const WINDOW = 28;

    private const MIN_DAYS = 14;

    private const MIN_COMBINED_VIEWS = 56;

    private const YEAR_SHIFT = 364;

    private const LIST_SIZE = 25;

    private const WEEKS = 12;

    private const PAGE_METRICS = ['home', 'search', 'geosearch', 'stations'];

    public function __construct(private readonly StatsService $stats) {}

    /**
     * @return array{views: Comparison, geosearch: Comparison, app_share: ?float, entry: array{home: float, search: float, geosearch: float, stations: float}, filtered: array{reload: int, bot: int, datacenter: int, foreign: int}}
     */
    public function overview(): array
    {
        $yesterday = $this->yesterday();
        $currentStart = $yesterday->copy()->subDays(self::WINDOW - 1);
        $aggregates = $this->aggregates(
            $currentStart->copy()->subDays(self::WINDOW + self::YEAR_SHIFT - self::WINDOW),
            $yesterday,
        );

        return [
            'views' => $this->comparison($aggregates, $yesterday, fn (array $agg): int => $this->humanViews($agg)),
            'geosearch' => $this->comparison($aggregates, $yesterday, fn (array $agg): int => (int) ($agg['geosearch'] ?? 0)),
            'app_share' => $this->appShare($aggregates, $currentStart, $yesterday),
            'entry' => $this->entryShares($aggregates, $currentStart, $yesterday),
            'filtered' => $this->filteredTotals($aggregates, $currentStart, $yesterday),
        ];
    }

    /**
     * @param  int  $months  3, 6 or 12
     * @return array{days: list<array{date: string, views: ?int, avg28: ?float, last_year_avg28: ?float}>, live_since: string}
     */
    public function trend(int $months): array
    {
        $yesterday = $this->yesterday();
        $first = $this->today()->subMonthsNoOverflow($months);
        $aggregates = $this->aggregates($first->copy()->subDays(self::YEAR_SHIFT + self::WINDOW - 1), $yesterday);
        $views = array_map(fn (?array $agg): ?int => $agg === null ? null : $this->humanViews($agg), $aggregates);

        $days = [];
        for ($day = $first->copy(); $day->lte($yesterday); $day->addDay()) {
            $days[] = [
                'date' => $day->format('Y-m-d'),
                'views' => $views[$day->format('Y_m_d')] ?? null,
                'avg28' => $this->rollingMean($views, $day),
                'last_year_avg28' => $this->rollingMean($views, $day->copy()->subDays(self::YEAR_SHIFT)),
            ];
        }

        return ['days' => $days, 'live_since' => $this->liveSince()->format('Y-m-d')];
    }

    /**
     * @param  string  $list  top|rising|falling|least
     * @return array{rows: list<array{name: string, codes: list<string>, per_day: float, change: ?float, weekly: list<int>}>, unused: int}
     */
    public function stations(string $list): array
    {
        $groups = $this->groups();
        $perDay = $this->groupViewsPerDay($groups, $this->yesterday(), self::WEEKS * 7);
        $summary = $this->summarise($groups, $perDay);

        $ids = array_map('strval', array_keys($groups));

        $ids = match ($list) {
            'rising', 'falling' => array_values(array_filter($ids, fn (string $id): bool => $summary[$id]['change'] !== null)),
            'top' => array_values(array_filter($ids, fn (string $id): bool => $summary[$id]['views'] > 0)),
            default => $ids,
        };

        usort($ids, fn (string $a, string $b): int => match ($list) {
            'rising' => [$summary[$b]['change'], $a] <=> [$summary[$a]['change'], $b],
            'falling' => [$summary[$a]['change'], $a] <=> [$summary[$b]['change'], $b],
            'least' => [$summary[$a]['views'], $a] <=> [$summary[$b]['views'], $b],
            default => [$summary[$b]['views'], $a] <=> [$summary[$a]['views'], $b],
        });

        $rows = [];
        foreach (array_slice($ids, 0, self::LIST_SIZE) as $id) {
            $rows[] = [
                'name' => $groups[$id]['name'],
                'codes' => $groups[$id]['codes'],
                'per_day' => round($summary[$id]['views'] / self::WINDOW, 2),
                'change' => $summary[$id]['change'],
                'weekly' => $this->weekly($perDay[$id]),
            ];
        }

        $unused = count(array_filter($summary, fn (array $row): bool => $row['views'] === 0));

        return ['rows' => $rows, 'unused' => $unused];
    }

    /**
     * Find a station group by exact code, else by name (exact, then first prefix match alphabetically).
     *
     * The direction split uses single-direction page views only; "both directions" pages
     * say nothing about which way people travel.
     *
     * @return array{name: string, codes: list<array{code: string, to_center: bool, share: ?float}>, per_day: float, change: ?float, rank: int, of: int, days: list<array{date: string, views: int, avg28: ?float}>}|null
     */
    public function lookup(string $term): ?array
    {
        $term = trim($term);
        $groups = $this->groups();
        $id = $this->findGroup($groups, $term);
        if ($id === null) {
            return null;
        }

        $yesterday = $this->yesterday();
        $summary = $this->summarise($groups, $this->groupViewsPerDay($groups, $yesterday, self::WINDOW * 2));
        $group = $groups[$id];

        $rank = 1 + count(array_filter($summary, fn (array $row): bool => $row['views'] > $summary[$id]['views']));

        return [
            'name' => $group['name'],
            'codes' => $this->directionShares($group, $yesterday),
            'per_day' => round($summary[$id]['views'] / self::WINDOW, 2),
            'change' => $summary[$id]['change'],
            'rank' => $rank,
            'of' => count($groups),
            'days' => $this->groupDays($group, $yesterday),
        ];
    }

    /**
     * @param  array<string, ?array<string, mixed>>  $aggregates  keyed by Y_m_d
     * @param  callable(array<string, mixed>): int  $metric
     * @return Comparison
     */
    private function comparison(array $aggregates, Carbon $yesterday, callable $metric): array
    {
        $values = array_map(fn (?array $agg): ?int => $agg === null ? null : $metric($agg), $aggregates);

        $current = $this->windowMean($values, $yesterday);
        $prevEnd = $yesterday->copy()->subDays(self::WINDOW);
        $yearEnd = $yesterday->copy()->subDays(self::YEAR_SHIFT);
        $prev = $this->windowMean($values, $prevEnd);
        $year = $this->windowMean($values, $yearEnd);

        $liveSince = $this->liveSince();
        $windowStart = fn (Carbon $end): Carbon => $end->copy()->subDays(self::WINDOW - 1);
        $year = $current === null ? null : $year;

        return [
            'avg' => $current === null ? null : round($current, 1),
            'vs_prev' => $this->percentChange($current, $prev),
            'vs_year' => $this->percentChange($current, $year),
            'straddles' => $windowStart($prevEnd)->lt($liveSince) || ($year !== null && $windowStart($yearEnd)->lt($liveSince)),
        ];
    }

    private function percentChange(?float $now, ?float $before): ?float
    {
        if ($now === null || $before === null || $before <= 0.0) {
            return null;
        }

        return round(($now / $before - 1) * 100, 1);
    }

    /**
     * Mean of the days present in the 28 days ending $end; null with fewer than 14.
     *
     * @param  array<string, ?int>  $values  keyed by Y_m_d
     */
    private function windowMean(array $values, Carbon $end): ?float
    {
        $sum = 0;
        $count = 0;
        for ($i = 0; $i < self::WINDOW; $i++) {
            $value = $values[$end->copy()->subDays($i)->format('Y_m_d')] ?? null;
            if ($value !== null) {
                $sum += $value;
                $count++;
            }
        }

        return $count >= self::MIN_DAYS ? $sum / $count : null;
    }

    /**
     * @param  array<string, ?int>  $values
     */
    private function rollingMean(array $values, Carbon $end): ?float
    {
        $mean = $this->windowMean($values, $end);

        return $mean === null ? null : round($mean, 1);
    }

    /**
     * @param  array<string, ?array<string, mixed>>  $aggregates
     */
    private function appShare(array $aggregates, Carbon $from, Carbon $to): ?float
    {
        $app = 0;
        $views = 0;
        $found = false;

        foreach ($this->windowAggregates($aggregates, $from, $to, requireFilterKeys: true) as $agg) {
            $app += (int) $agg['app'];
            $views += $this->humanViews($agg);
            $found = true;
        }

        return $found && $views > 0 ? $app / $views : null;
    }

    /**
     * @param  array<string, ?array<string, mixed>>  $aggregates
     * @return array{reload: int, bot: int, datacenter: int, foreign: int}
     */
    private function filteredTotals(array $aggregates, Carbon $from, Carbon $to): array
    {
        $totals = ['reload' => 0, 'bot' => 0, 'datacenter' => 0, 'foreign' => 0];

        foreach ($this->windowAggregates($aggregates, $from, $to, requireFilterKeys: true) as $agg) {
            foreach (array_keys($totals) as $reason) {
                $totals[$reason] += (int) ($agg['filtered'][$reason] ?? 0);
            }
        }

        return $totals;
    }

    /**
     * @param  array<string, ?array<string, mixed>>  $aggregates
     * @return array{home: float, search: float, geosearch: float, stations: float}
     */
    private function entryShares(array $aggregates, Carbon $from, Carbon $to): array
    {
        $sums = array_fill_keys(self::PAGE_METRICS, 0);
        foreach ($this->windowAggregates($aggregates, $from, $to) as $agg) {
            foreach (self::PAGE_METRICS as $metric) {
                $sums[$metric] += (int) ($agg[$metric] ?? 0);
            }
        }

        $total = array_sum($sums);

        return [
            'home' => $total > 0 ? $sums['home'] / $total : 0.0,
            'search' => $total > 0 ? $sums['search'] / $total : 0.0,
            'geosearch' => $total > 0 ? $sums['geosearch'] / $total : 0.0,
            'stations' => $total > 0 ? $sums['stations'] / $total : 0.0,
        ];
    }

    /**
     * Aggregates in a date range. With $requireFilterKeys, only days on or after go-live
     * that carry the app and filtered fields.
     *
     * @param  array<string, ?array<string, mixed>>  $aggregates
     * @return list<array<string, mixed>>
     */
    private function windowAggregates(array $aggregates, Carbon $from, Carbon $to, bool $requireFilterKeys = false): array
    {
        $liveSince = $this->liveSince();
        $found = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $agg = $aggregates[$day->format('Y_m_d')] ?? null;
            if ($agg === null) {
                continue;
            }
            if ($requireFilterKeys && ($day->lt($liveSince) || ! isset($agg['app']) || ! is_array($agg['filtered'] ?? null))) {
                continue;
            }
            $found[] = $agg;
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $aggregate
     */
    private function humanViews(array $aggregate): int
    {
        return array_sum(array_map(fn (string $metric): int => (int) ($aggregate[$metric] ?? 0), self::PAGE_METRICS));
    }

    /**
     * @return array<string, ?array<string, mixed>> keyed by Y_m_d, oldest first
     */
    private function aggregates(Carbon $from, Carbon $to): array
    {
        $dates = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dates[] = $day->format('Y_m_d');
        }

        return $this->stats->getAggregatedDays($dates);
    }

    /**
     * Station groups keyed by normalised name; both directions of a stop share one group.
     *
     * @return array<string, Group>
     */
    private function groups(): array
    {
        $groups = [];
        $stations = Station::query()->whereNotNull('code')->orderBy('code')->get(['code', 'name', 'is_direction_to_center']);

        foreach ($stations as $station) {
            $key = StationName::key((string) $station->name);
            $groups[$key] ??= ['name' => trim((string) $station->name), 'codes' => [], 'toCenter' => []];
            $groups[$key]['codes'][] = (string) $station->code;
            $groups[$key]['toCenter'][(string) $station->code] = $station->is_direction_to_center;
        }

        return $groups;
    }

    /**
     * @param  array<string, Group>  $groups
     */
    private function findGroup(array $groups, string $term): ?string
    {
        if ($term === '') {
            return null;
        }

        foreach ($groups as $id => $group) {
            if (in_array($term, $group['codes'], true)) {
                return (string) $id;
            }
        }

        $key = StationName::key($term);
        if (isset($groups[$key])) {
            return $key;
        }

        $matches = array_filter(array_keys($groups), fn (int|string $id): bool => str_starts_with((string) $id, $key));
        sort($matches);

        return $matches === [] ? null : (string) $matches[0];
    }

    /**
     * Views per group per day for the last $days days: index 0 is yesterday, 1 the day before, ...
     *
     * @param  array<string, Group>  $groups
     * @return array<string, list<int>>
     */
    private function groupViewsPerDay(array $groups, Carbon $yesterday, int $days): array
    {
        $groupByCode = [];
        foreach ($groups as $id => $group) {
            foreach ($group['codes'] as $code) {
                $groupByCode[$code] = (string) $id;
            }
        }

        $keys = [];
        for ($i = 0; $i < $days; $i++) {
            $keys[] = 'stats_day_'.$yesterday->copy()->subDays($i)->format('Y_m_d');
        }

        $hashes = $this->pipelined(function ($pipe) use ($keys): void {
            foreach ($keys as $key) {
                $pipe->hgetall($key);
            }
        });

        $perDay = array_fill_keys(array_map('strval', array_keys($groups)), array_fill(0, $days, 0));
        foreach ($hashes as $index => $hash) {
            foreach (is_array($hash) ? $hash : [] as $field => $count) {
                if (preg_match('/^station_(\d+)(?:_all)?$/', (string) $field, $matches) && isset($groupByCode[$matches[1]])) {
                    $perDay[$groupByCode[$matches[1]]][$index] += (int) $count;
                }
            }
        }

        return $perDay;
    }

    /**
     * 28-day views and the share-based change for each group.
     *
     * @param  array<string, Group>  $groups
     * @param  array<string, list<int>>  $perDay  needs at least 56 days
     * @return array<string, array{views: int, change: ?float}>
     */
    private function summarise(array $groups, array $perDay): array
    {
        $now = [];
        $prev = [];
        foreach ($perDay as $id => $days) {
            $now[$id] = array_sum(array_slice($days, 0, self::WINDOW));
            $prev[$id] = array_sum(array_slice($days, self::WINDOW, self::WINDOW));
        }
        $totalNow = array_sum($now);
        $totalPrev = array_sum($prev);

        $summary = [];
        foreach (array_keys($groups) as $id) {
            $id = (string) $id;
            $change = null;
            if ($prev[$id] > 0 && $totalNow > 0 && $now[$id] + $prev[$id] >= self::MIN_COMBINED_VIEWS) {
                $change = round((($now[$id] / $totalNow) / ($prev[$id] / $totalPrev) - 1) * 100, 1);
            }
            $summary[$id] = ['views' => $now[$id], 'change' => $change];
        }

        return $summary;
    }

    /**
     * @param  list<int>  $days  index 0 = yesterday
     * @return list<int> 12 weekly sums, oldest first
     */
    private function weekly(array $days): array
    {
        $weeks = [];
        for ($week = self::WEEKS - 1; $week >= 0; $week--) {
            $weeks[] = array_sum(array_slice($days, $week * 7, 7));
        }

        return $weeks;
    }

    /**
     * @param  Group  $group
     * @return list<array{code: string, to_center: bool, share: ?float}>
     */
    private function directionShares(array $group, Carbon $yesterday): array
    {
        $totals = [];
        foreach ($group['codes'] as $code) {
            $totals[$code] = 0;
        }

        $fields = array_map(fn (string $code): string => "station_{$code}", $group['codes']);
        foreach ($this->groupFieldDays($fields, $yesterday, self::WINDOW) as $values) {
            foreach ($group['codes'] as $index => $code) {
                $totals[$code] += $values[$index];
            }
        }

        $sum = array_sum($totals);

        return array_map(fn (string $code): array => [
            'code' => $code,
            'to_center' => (bool) $group['toCenter'][$code],
            'share' => $sum > 0 ? $totals[$code] / $sum : null,
        ], $group['codes']);
    }

    /**
     * Daily views of a group for the last 365 days with a rolling 28-day mean.
     *
     * @param  Group  $group
     * @return list<array{date: string, views: int, avg28: ?float}>
     */
    private function groupDays(array $group, Carbon $yesterday): array
    {
        $fields = [];
        foreach ($group['codes'] as $code) {
            $fields[] = "station_{$code}";
            $fields[] = "station_{$code}_all";
        }

        $lookback = 365 + self::WINDOW - 1;
        $daily = array_map('array_sum', $this->groupFieldDays($fields, $yesterday, $lookback));

        $days = [];
        for ($i = 364; $i >= 0; $i--) {
            $window = array_slice($daily, $i, self::WINDOW);
            $days[] = [
                'date' => $yesterday->copy()->subDays($i)->format('Y-m-d'),
                'views' => $daily[$i],
                'avg28' => round(array_sum($window) / self::WINDOW, 1),
            ];
        }

        return $days;
    }

    /**
     * HMGET of $fields from each of the last $days day hashes; index 0 = yesterday.
     *
     * @param  list<string>  $fields
     * @return list<list<int>>
     */
    private function groupFieldDays(array $fields, Carbon $yesterday, int $days): array
    {
        $results = $this->pipelined(function ($pipe) use ($fields, $yesterday, $days): void {
            for ($i = 0; $i < $days; $i++) {
                $pipe->hmget('stats_day_'.$yesterday->copy()->subDays($i)->format('Y_m_d'), $fields);
            }
        });

        return array_map(
            fn (mixed $values): array => array_map(fn (mixed $value): int => (int) $value, is_array($values) ? array_values($values) : array_fill(0, count($fields), 0)),
            $results,
        );
    }

    /**
     * Run Redis commands in one pipeline and return the replies in order.
     *
     * @param  callable(mixed): void  $queue  receives the pipeline
     * @return list<mixed>
     */
    private function pipelined(callable $queue): array
    {
        // Larastan resolves the connection to the phpredis stub, whose pipeline() takes no callback.
        // @phpstan-ignore arguments.count
        $replies = Redis::connection()->pipeline($queue);

        return array_values((array) $replies);
    }

    private function today(): Carbon
    {
        return Carbon::now()->startOfDay();
    }

    private function yesterday(): Carbon
    {
        return $this->today()->subDay();
    }

    private function liveSince(): Carbon
    {
        return Carbon::parse((string) config('stats.filters_live_since'))->startOfDay();
    }
}
