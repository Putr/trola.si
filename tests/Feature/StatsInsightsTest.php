<?php

use App\Models\Station;
use App\Services\Stats\StatsInsights;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->travelTo('2026-12-31 12:00');
    config(['stats.filters_live_since' => '2026-11-07']);
});

/**
 * Seed one human-views aggregate per day from $from to $to (inclusive).
 *
 * @param  array<string, mixed>  $extra
 */
function insightsSeedRange(string $from, string $to, int $views, array $extra = []): void
{
    for ($day = now()->parse($from); $day->lte(now()->parse($to)); $day->addDay()) {
        seedAgg($day->format('Y_m_d'), $views, 0, $extra);
    }
}

/**
 * Seed one station-field count on each day of a range.
 */
function insightsSeedStationRange(string $from, string $to, string $field, int $perDay): void
{
    for ($day = now()->parse($from); $day->lte(now()->parse($to)); $day->addDay()) {
        seedStationViews($day->format('Y_m_d'), [$field => $perDay]);
    }
}

function insightsDay(CarbonInterface|string $date): string
{
    return now()->parse($date)->format('Y_m_d');
}

describe('overview', function () {
    it('averages the last 28 days and compares with the 28 before', function () {
        insightsSeedRange('2026-11-05', '2026-12-02', 100);
        insightsSeedRange('2026-12-03', '2026-12-30', 110);

        $overview = app(StatsInsights::class)->overview();

        expect($overview['views']['avg'])->toBe(110.0)
            ->and($overview['views']['vs_prev'])->toBe(10.0);
    });

    it('has no year comparison when the year-ago days are missing', function () {
        insightsSeedRange('2026-11-05', '2026-12-30', 100);

        expect(app(StatsInsights::class)->overview()['views']['vs_year'])->toBeNull();
    });

    it('compares with the same 28 days a year earlier, shifted by 364 days', function () {
        insightsSeedRange('2026-12-03', '2026-12-30', 120);
        insightsSeedRange('2025-12-04', '2025-12-31', 100);

        expect(app(StatsInsights::class)->overview()['views']['vs_year'])->toBe(20.0);
    });

    it('returns null comparisons when fewer than 14 days exist', function () {
        insightsSeedRange('2026-12-20', '2026-12-30', 100);

        $overview = app(StatsInsights::class)->overview();

        expect($overview['views']['avg'])->toBeNull()
            ->and($overview['views']['vs_prev'])->toBeNull()
            ->and($overview['geosearch']['avg'])->toBeNull();
    });

    it('flags a comparison window that includes days before the filters went live', function () {
        insightsSeedRange('2026-11-05', '2026-12-30', 100);

        expect(app(StatsInsights::class)->overview()['views']['straddles'])->toBeTrue();
    });

    it('does not flag windows that sit fully after go-live', function () {
        config(['stats.filters_live_since' => '2026-10-01']);
        insightsSeedRange('2026-11-05', '2026-12-30', 100);

        expect(app(StatsInsights::class)->overview()['views']['straddles'])->toBeFalse();
    });

    it('computes the app share and filtered totals from post go-live days only', function () {
        insightsSeedRange('2026-12-03', '2026-12-30', 120, ['app' => 30, 'filtered' => ['reload' => 5, 'bot' => 2, 'datacenter' => 1, 'foreign' => 0]]);
        // Before go-live the unfiltered app count must not matter.
        seedAgg('2026_11_06', 500, 0, ['app' => 500]);

        $overview = app(StatsInsights::class)->overview();

        expect($overview['app_share'])->toBe(0.25)
            ->and($overview['filtered'])->toBe(['reload' => 140, 'bot' => 56, 'datacenter' => 28, 'foreign' => 0]);
    });

    it('ignores aggregates without app and filtered keys', function () {
        insightsSeedRange('2026-12-03', '2026-12-30', 100);

        $overview = app(StatsInsights::class)->overview();

        expect($overview['app_share'])->toBeNull()
            ->and($overview['filtered'])->toBe(['reload' => 0, 'bot' => 0, 'datacenter' => 0, 'foreign' => 0]);
    });

    it('shares the entry pages over the last 28 days', function () {
        for ($day = now()->parse('2026-12-03'); $day->lte(now()->parse('2026-12-30')); $day->addDay()) {
            seedAgg($day->format('Y_m_d'), 10, 60, ['search' => 20, 'geosearch' => 10]);
        }

        expect(app(StatsInsights::class)->overview()['entry'])->toBe(['home' => 0.1, 'search' => 0.2, 'geosearch' => 0.1, 'stations' => 0.6]);
    });

    it('sums home, search, geosearch and stations as human views', function () {
        insightsSeedRange('2026-12-03', '2026-12-30', 0);
        for ($day = now()->parse('2026-12-03'); $day->lte(now()->parse('2026-12-30')); $day->addDay()) {
            seedAgg($day->format('Y_m_d'), 10, 60, ['search' => 20, 'geosearch' => 10]);
        }
        $overview = app(StatsInsights::class)->overview();

        expect($overview['views']['avg'])->toBe(100.0)
            ->and($overview['geosearch']['avg'])->toBe(10.0);
    });
});

describe('trend', function () {
    it('covers every day from three months back to yesterday', function () {
        insightsSeedRange('2026-09-30', '2026-12-30', 100);

        $trend = app(StatsInsights::class)->trend(3);

        expect($trend['days'])->toHaveCount(92)
            ->and($trend['days'][0]['date'])->toBe('2026-09-30')
            ->and($trend['days'][91]['date'])->toBe('2026-12-30')
            ->and($trend['live_since'])->toBe('2026-11-07');
    });

    it('shows a missing day as null without dragging the average down', function () {
        insightsSeedRange('2026-09-30', '2026-12-30', 100);
        Redis::del('stats_agg_day_2026_12_15');

        $days = collect(app(StatsInsights::class)->trend(3)['days'])->keyBy('date');

        expect($days['2026-12-15']['views'])->toBeNull()
            ->and($days['2026-12-15']['avg28'])->toBe(100.0)
            ->and($days['2026-12-30']['avg28'])->toBe(100.0);
    });

    it('has no rolling average with fewer than 14 days behind it', function () {
        insightsSeedRange('2026-12-01', '2026-12-30', 100);

        $days = collect(app(StatsInsights::class)->trend(3)['days'])->keyBy('date');

        expect($days['2026-12-05']['avg28'])->toBeNull()
            ->and($days['2026-12-30']['avg28'])->toBe(100.0);
    });

    it('adds the rolling average of the same days a year earlier', function () {
        insightsSeedRange('2026-09-30', '2026-12-30', 100);
        insightsSeedRange('2025-09-01', '2025-12-31', 80);

        $days = collect(app(StatsInsights::class)->trend(3)['days'])->keyBy('date');

        expect($days['2026-12-30']['last_year_avg28'])->toBe(80.0);
    });
});

describe('stations', function () {
    beforeEach(function () {
        Station::factory()->create(['code' => '600011', 'name' => 'Konzorcij', 'is_direction_to_center' => true]);
        Station::factory()->create(['code' => '600012', 'name' => 'konzorcij ', 'is_direction_to_center' => false]);
        Station::factory()->create(['code' => '600021', 'name' => 'Šiška']);
        Station::factory()->create(['code' => '600031', 'name' => 'Vič']);
    });

    it('groups codes with the same name and sums single and both-direction views', function () {
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600011', 2);
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600012_all', 3);

        $rows = app(StatsInsights::class)->stations('top')['rows'];

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['name'])->toBe('Konzorcij')
            ->and($rows[0]['codes'])->toBe(['600011', '600012'])
            ->and($rows[0]['per_day'])->toBe(5.0)
            ->and($rows[0]['weekly'])->toHaveCount(12)
            ->and($rows[0]['weekly'][11])->toBe(35);
    });

    it('counts unused groups and lists them first under least used', function () {
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600011', 2);

        $result = app(StatsInsights::class)->stations('least');

        expect($result['unused'])->toBe(2)
            ->and(collect($result['rows'])->pluck('name')->all())->toBe(['Šiška', 'Vič', 'Konzorcij']);
    });

    it('measures change as the shift in share of all station views', function () {
        // Konzorcij: 10 of 100 per window before, 20 of 100 now -> share doubles.
        insightsSeedStationRange('2026-11-05', '2026-12-02', 'station_600011', 10);
        insightsSeedStationRange('2026-11-05', '2026-12-02', 'station_600021', 90);
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600011', 20);
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600021', 80);

        $rising = app(StatsInsights::class)->stations('rising')['rows'];
        $falling = app(StatsInsights::class)->stations('falling')['rows'];

        expect($rising[0]['name'])->toBe('Konzorcij')
            ->and($rising[0]['change'])->toBe(100.0)
            ->and($falling[0]['name'])->toBe('Šiška')
            ->and($falling[0]['change'])->toBe(-11.1);
    });

    it('lists only gains as rising and only losses as falling', function () {
        insightsSeedStationRange('2026-11-05', '2026-12-02', 'station_600011', 10);
        insightsSeedStationRange('2026-11-05', '2026-12-02', 'station_600021', 90);
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600011', 20);
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600021', 80);

        $insights = app(StatsInsights::class);

        expect(collect($insights->stations('rising')['rows'])->pluck('name')->all())->toBe(['Konzorcij'])
            ->and(collect($insights->stations('falling')['rows'])->pluck('name')->all())->toBe(['Šiška']);
    });

    it('gives no change to a group with too few views, and keeps it out of rising and falling', function () {
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600021', 50);
        seedStationViews('2026_11_10', ['station_600011' => 1]);
        seedStationViews('2026_12_10', ['station_600011' => 2]);

        $insights = app(StatsInsights::class);
        $top = collect($insights->stations('top')['rows'])->keyBy('name');

        expect($top['Konzorcij']['change'])->toBeNull()
            ->and(collect($insights->stations('rising')['rows'])->pluck('name')->all())->not->toContain('Konzorcij')
            ->and(collect($insights->stations('falling')['rows'])->pluck('name')->all())->not->toContain('Konzorcij');
    });

    it('gives no change to a group without views in the previous window', function () {
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600011', 10);

        expect(app(StatsInsights::class)->stations('top')['rows'][0]['change'])->toBeNull();
    });

    it('returns at most 25 rows and ignores views of codes that are not stations', function () {
        for ($i = 0; $i < 30; $i++) {
            Station::factory()->create(['code' => (string) (700000 + $i), 'name' => "Postaja {$i}"]);
        }
        seedStationViews('2026_12_20', ['station_999999' => 500]);

        $result = app(StatsInsights::class)->stations('least');

        expect($result['rows'])->toHaveCount(25)
            ->and($result['unused'])->toBe(33);
    });
});

describe('lookup', function () {
    beforeEach(function () {
        Station::factory()->create(['code' => '600011', 'name' => 'Konzorcij', 'is_direction_to_center' => true]);
        Station::factory()->create(['code' => '600012', 'name' => 'Konzorcij', 'is_direction_to_center' => false]);
        Station::factory()->create(['code' => '600021', 'name' => 'Šiška', 'is_direction_to_center' => true]);
        Station::factory()->create(['code' => '600031', 'name' => 'Šiška bolnica', 'is_direction_to_center' => true]);
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600011', 15);
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600012', 5);
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600021', 50);
        insightsSeedStationRange('2026-12-03', '2026-12-30', 'station_600031', 1);
    });

    it('finds a group by exact code or by name', function () {
        $insights = app(StatsInsights::class);

        expect($insights->lookup('600012')['name'])->toBe('Konzorcij')
            ->and($insights->lookup('konzorcij')['name'])->toBe('Konzorcij')
            ->and($insights->lookup('nonexistent'))->toBeNull()
            ->and($insights->lookup('  '))->toBeNull()
            ->and($insights->lookup('🚌'))->toBeNull()
            ->and($insights->lookup('中文'))->toBeNull();
    });

    it('prefers an exact name over a prefix match, then the first prefix match alphabetically', function () {
        $insights = app(StatsInsights::class);

        expect($insights->lookup('siska')['name'])->toBe('Šiška')
            ->and($insights->lookup('sis')['name'])->toBe('Šiška')
            ->and($insights->lookup('SISKA B')['name'])->toBe('Šiška bolnica');
    });

    it('ranks the group among all groups by 28-day views', function () {
        $result = app(StatsInsights::class)->lookup('konzorcij');

        expect($result['rank'])->toBe(2)
            ->and($result['of'])->toBe(3)
            ->and($result['per_day'])->toBe(20.0);
    });

    it('splits the views between directions so the shares sum to 1', function () {
        $codes = collect(app(StatsInsights::class)->lookup('konzorcij')['codes'])->keyBy('code');

        expect($codes['600011']['share'])->toBe(0.75)
            ->and($codes['600011']['to_center'])->toBeTrue()
            ->and($codes['600012']['share'])->toBe(0.25)
            ->and($codes['600012']['to_center'])->toBeFalse()
            ->and($codes->sum('share'))->toBe(1.0);
    });

    it('returns 365 days with a rolling average', function () {
        $days = app(StatsInsights::class)->lookup('konzorcij')['days'];

        expect($days)->toHaveCount(365)
            ->and($days[364])->toBe(['date' => '2026-12-30', 'views' => 20, 'avg28' => 20.0])
            ->and($days[0]['date'])->toBe('2025-12-31');
    });

    it('has no change when the previous window was empty', function () {
        expect(app(StatsInsights::class)->lookup('konzorcij')['change'])->toBeNull();
    });
});
