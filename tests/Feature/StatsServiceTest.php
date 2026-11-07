<?php

use App\Enums\FilterReason;
use App\Services\Stats\StatsInsights;
use App\Services\StatsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Redis;

function setViews(CarbonInterface $date, string $page, int $views): void
{
    Redis::hset('stats_day_'.$date->format('Y_m_d'), $page, $views);
}

it('aggregates a day with totals, difference from the weekday average and year-over-year', function () {
    $this->travelTo('2026-11-04 12:00'); // a Wednesday
    $today = now();
    setViews($today, 'home', 30);
    setViews($today, 'search', 10);
    setViews($today, 'station_600011', 7);
    setViews($today, 'station_600011_all', 3);
    setViews($today->copy()->subWeek(), 'home', 10);
    setViews($today->copy()->subYear(), 'home', 15);

    $stats = app(StatsService::class);
    $stats->calculateRollingAverages(3);
    $day = $stats->aggregateDay($today->format('Y_m_d'), true);

    expect($day)->toMatchArray([
        'date' => '2026-11-04',
        'home' => 30,
        'search' => 10,
        'geosearch' => 0,
        'stations' => 10,
    ])
        ->and($day['yoy']['home'])->toBe(['diff' => 15, 'pct' => 100.0])
        // The weekday average spans every Wednesday of the last 3 months, today included: 40 / 14.
        ->and($day['diff_from_avg']['home'])->toEqual(27.14)
        ->and($stats->getAggregatedDay('2026_11_04'))->toEqual($day);
});

it('counts station views under the production key prefix', function () {
    useRedisPrefix('trolasi_database_');
    $date = now()->format('Y_m_d');
    app(StatsService::class)->trackPageView('station_600011');
    app(StatsService::class)->trackPageView('station_600011');
    app(StatsService::class)->trackPageView('station_600012_all');

    expect(app(StatsService::class)->getStationViewsForDate($date))->toBe(3)
        ->and(Redis::hget("stats_day_{$date}", 'station_600011'))->toBe('2');

    Redis::flushdb();
});

it('recalculates past days with --force', function () {
    $yesterday = now()->subDay();
    setViews($yesterday, 'home', 1);
    $this->artisan('stats:aggregate --days=2')->assertSuccessful();

    setViews($yesterday, 'home', 6);
    $this->artisan('stats:aggregate --days=2 --force')->assertSuccessful();

    expect(app(StatsService::class)->getAggregatedDay($yesterday->format('Y_m_d'))['home'])->toBe(6);
});

it('fails the aggregate command when Redis is down', function () {
    Redis::shouldReceive('get', 'set', 'exists', 'hget', 'hgetall', 'connection')->andThrow(new RuntimeException('Redis down'));

    $this->artisan('stats:aggregate --today-only')->assertFailed();
});

it('still renders pages when Redis is down', function () {
    Redis::shouldReceive('hincrby')->andThrow(new RuntimeException('Redis down'));

    $this->get('/navodila')->assertOk();
});

it('includes the last hour of yesterday in the nightly run', function () {
    $this->travelTo('2026-11-04 23:00');
    Redis::hset('stats_day_2026_11_04', 'home', 3);
    $this->artisan('stats:aggregate --today-only')->assertSuccessful();

    Redis::hset('stats_day_2026_11_04', 'home', 5); // views between 23:00 and midnight
    $this->travelTo('2026-11-05 02:00');
    $this->artisan('stats:aggregate --days=30')->assertSuccessful();

    expect(app(StatsService::class)->getAggregatedDay('2026_11_04')['home'])->toBe(5);
});

it('tracks a page view as a hash increment on the day key', function () {
    $this->travelTo('2026-11-07 12:00');
    $stats = app(StatsService::class);
    $stats->trackPageView('home');
    $stats->trackPageView('home');
    $stats->trackPageView('station_600011_all');

    expect(Redis::hgetall('stats_day_2026_11_07'))->toBe(['home' => '2', 'station_600011_all' => '1'])
        ->and(Redis::keys('stats_2026*'))->toBe([]);
});

it('reads totals without ever scanning the keyspace', function () {
    $this->travelTo('2026-11-04 12:00');
    setViews(now(), 'home', 3);
    setViews(now(), 'station_600011', 4);
    Redis::spy();
    Redis::shouldReceive('hget')->andReturn('3');
    Redis::shouldReceive('hgetall')->andReturn(['station_600011' => '4']);
    Redis::shouldReceive('get')->andReturn(null);
    Redis::shouldReceive('set');
    Redis::shouldNotReceive('scan');
    Redis::shouldNotReceive('keys');

    $stats = app(StatsService::class);
    $stats->calculateRollingAverages(3);
    $stats->aggregateDay('2026_11_04', true);
});

describe('stats:migrate-to-hashes', function () {
    function seedLegacy(): void
    {
        Redis::set('stats_2026_11_01_home', 5);
        Redis::set('stats_2026_11_01_station_600011', 4);
        Redis::set('stats_2026_11_02_station_600011_all', 3);
        Redis::set('stats_agg_day_2026_11_01', '{"home":5}');
        Redis::set('stats_avg_dow_1', '{"home":1}');
    }

    it('only reports on a dry run', function () {
        seedLegacy();

        $this->artisan('stats:migrate-to-hashes')
            ->expectsOutputToContain('Keys would fold: 3; dates: 2; total views: 12.')
            ->assertSuccessful();

        expect(Redis::get('stats_2026_11_01_home'))->toBe('5')
            ->and(Redis::exists('stats_day_2026_11_01'))->toBe(0);
    });

    it('folds legacy keys into day hashes and removes them', function () {
        seedLegacy();

        $this->artisan('stats:migrate-to-hashes --execute')
            ->expectsOutputToContain('Keys folded: 3; dates: 2; total views: 12.')
            ->assertSuccessful();

        expect(Redis::hgetall('stats_day_2026_11_01'))->toBe(['home' => '5', 'station_600011' => '4'])
            ->and(Redis::hgetall('stats_day_2026_11_02'))->toBe(['station_600011_all' => '3'])
            ->and(Redis::exists('stats_2026_11_01_home', 'stats_2026_11_01_station_600011', 'stats_2026_11_02_station_600011_all'))->toBe(0)
            ->and(Redis::get('stats_agg_day_2026_11_01'))->toBe('{"home":5}')
            ->and(Redis::get('stats_avg_dow_1'))->toBe('{"home":1}');
    });

    it('is a no-op when run again', function () {
        seedLegacy();
        $this->artisan('stats:migrate-to-hashes --execute')->assertSuccessful();

        $this->artisan('stats:migrate-to-hashes --execute')
            ->expectsOutputToContain('Keys folded: 0;')
            ->assertSuccessful();

        expect(Redis::hget('stats_day_2026_11_01', 'home'))->toBe('5');
    });

    it('adds legacy views on top of views already written to the hash', function () {
        seedLegacy();
        Redis::hincrby('stats_day_2026_11_01', 'home', 2);

        $this->artisan('stats:migrate-to-hashes --execute')->assertSuccessful();

        expect(Redis::hget('stats_day_2026_11_01', 'home'))->toBe('7');
    });

    it('keeps numbers identical after migrating', function () {
        seedLegacy();
        $this->artisan('stats:migrate-to-hashes --execute')->assertSuccessful();

        expect(app(StatsService::class)->getDailyStats('2026_11_01'))->toMatchArray(['home' => 5, 'stations' => 4]);
    });

    it('works under the production key prefix', function () {
        useRedisPrefix('trolasi_database_');
        seedLegacy();
        expect(rawRedisKeys())->each->toStartWith('trolasi_database_');
        Redis::hincrby('stats_day_2026_11_01', 'home', 2);

        $this->artisan('stats:migrate-to-hashes')
            ->expectsOutputToContain('Keys would fold: 3;')
            ->assertSuccessful();
        $this->artisan('stats:migrate-to-hashes --execute')
            ->expectsOutputToContain('Keys folded: 3; dates: 2; total views: 12.')
            ->assertSuccessful();
        $this->artisan('stats:migrate-to-hashes --execute')
            ->expectsOutputToContain('Keys folded: 0;')
            ->assertSuccessful();

        expect(Redis::hget('stats_day_2026_11_01', 'home'))->toBe('7')
            ->and(Redis::hget('stats_day_2026_11_02', 'station_600011_all'))->toBe('3')
            ->and(Redis::exists('stats_2026_11_01_home'))->toBe(0)
            ->and(Redis::get('stats_agg_day_2026_11_01'))->toBe('{"home":5}');

        Redis::flushdb();
    });

    it('fails when Redis is down', function () {
        Redis::shouldReceive('scan')->andThrow(new RuntimeException('Redis down'));

        $this->artisan('stats:migrate-to-hashes --execute')->assertFailed();
    });
});

/**
 * Rebuild the Redis manager with a key prefix; changing config alone keeps the old connection.
 */
function useRedisPrefix(string $prefix): void
{
    config(['database.redis.options.prefix' => $prefix]);
    app()->forgetInstance('redis');
    Redis::clearResolvedInstance('redis');
}

/**
 * Every key in the test database as Redis stores it (with any prefix).
 *
 * @return list<string>
 */
function rawRedisKeys(): array
{
    return Redis::connection()->client()->executeRaw(['KEYS', '*']);
}

it('records app views and filtered reasons without touching page totals', function () {
    $service = app(StatsService::class);
    $service->trackPageView('station_600011', fromApp: true);
    $service->trackPageView('station_600011');
    $service->trackFiltered(FilterReason::Bot);
    $service->trackFiltered(FilterReason::Bot);
    $service->trackFiltered(FilterReason::Foreign);

    $key = 'stats_day_'.now()->format('Y_m_d');
    expect(Redis::hget($key, 'station_600011'))->toBe('2')
        ->and(Redis::hget($key, 'app'))->toBe('1')
        ->and(Redis::hget($key, 'filtered:bot'))->toBe('2')
        ->and(Redis::hget($key, 'filtered:foreign'))->toBe('1');
});

it('aggregates app views and filtered counts, with zeros when absent', function () {
    $service = app(StatsService::class);
    $today = now()->format('Y_m_d');

    expect($service->aggregateDay($today, forceRecalculate: true))
        ->toMatchArray(['app' => 0, 'filtered' => ['reload' => 0, 'bot' => 0, 'datacenter' => 0, 'foreign' => 0]]);

    $service->trackPageView('home', fromApp: true);
    $service->trackFiltered(FilterReason::Reload);
    $service->trackFiltered(FilterReason::Datacenter);
    $service->trackFiltered(FilterReason::Datacenter);

    expect($service->aggregateDay($today, forceRecalculate: true))
        ->toMatchArray(['home' => 1, 'app' => 1, 'filtered' => ['reload' => 1, 'bot' => 0, 'datacenter' => 2, 'foreign' => 0]]);
});

it('keeps app and filtered fields out of station totals', function () {
    $service = app(StatsService::class);
    $service->trackPageView('station_600011', fromApp: true);
    $service->trackFiltered(FilterReason::Bot);

    $today = now()->format('Y_m_d');
    expect($service->getStationViewsForDate($today))->toBe(1)
        ->and($service->aggregateDay($today, forceRecalculate: true)['stations'])->toBe(1);
});

it('keeps a day without a day hash missing instead of writing zeros', function () {
    $this->travelTo('2026-11-10 12:00');
    Redis::hset('stats_day_2026_11_08', 'home', 4);
    Redis::hset('stats_day_2026_11_06', 'home', 3);

    $this->artisan('stats:aggregate --days=5 --force')->assertSuccessful();

    $stats = app(StatsService::class);
    expect($stats->getAggregatedDay('2026_11_07'))->toBeNull()
        ->and($stats->getAggregatedDay('2026_11_09'))->toBeNull()
        ->and($stats->getAggregatedDay('2026_11_08')['home'])->toBe(4)
        ->and($stats->aggregateDay('2026_11_07', true)['home'])->toBe(0);

    $trend = collect(app(StatsInsights::class)->trend(3)['days'])->keyBy('date');
    expect($trend['2026-11-07']['views'])->toBeNull()
        ->and($trend['2026-11-08']['views'])->toBe(4);
});

it('prunes aggregates of days that have no day hash', function () {
    $this->travelTo('2026-11-10 12:00');
    Redis::hset('stats_day_2026_11_08', 'home', 4);
    Redis::set('stats_agg_day_2026_11_07', json_encode(['date' => '2026-11-07', 'home' => 0, 'stations' => 0]));
    Redis::set('stats_agg_day_2026_11_08', json_encode(['date' => '2026-11-08', 'home' => 4, 'stations' => 0]));

    $this->artisan('stats:aggregate --days=5 --prune-empty')->assertSuccessful();

    $stats = app(StatsService::class);
    expect($stats->getAggregatedDay('2026_11_07'))->toBeNull()
        ->and($stats->getAggregatedDay('2026_11_08'))->not->toBeNull();
});
