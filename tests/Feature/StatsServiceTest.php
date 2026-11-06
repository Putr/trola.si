<?php

use App\Models\Station;
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
    config(['database.redis.options.prefix' => 'trolasi_database_']);
    Redis::purge();
    $date = now()->format('Y_m_d');
    app(StatsService::class)->trackPageView('station_600011');
    app(StatsService::class)->trackPageView('station_600011');
    app(StatsService::class)->trackPageView('station_600012_all');

    expect(app(StatsService::class)->getStationViewsForDate($date))->toBe(3)
        ->and(Redis::hget("stats_day_{$date}", 'station_600011'))->toBe('2');

    Redis::flushdb();
});

it('lists popular pages with station names and week-over-week change', function () {
    Station::factory()->create(['code' => '600011', 'name' => 'Bavarski dvor']);
    setViews(now(), 'station_600011_all', 8);
    setViews(now()->subDays(8), 'station_600011_all', 4);
    setViews(now(), 'home', 2);

    $pages = app(StatsService::class)->getPopularPages(7, 10)->values();

    expect($pages[0])->toMatchArray([
        'name' => 'Station: Bavarski dvor (Both Directions)',
        'url' => '/600011/all',
        'views' => 8,
    ])
        ->and($pages[0]['trends']['wow'])->toBe(['type' => 'WoW', 'diff' => 4, 'pct' => 100.0])
        ->and($pages[1]['name'])->toBe('Home Page');
});

it('caches the stats page for ten minutes', function () {
    setViews(now(), 'home', 5);
    $this->get('/stats')->assertOk();

    setViews(now(), 'home', 9);
    expect($this->get('/stats')->viewData('stats')->first()['home'])->toBe(5);

    $this->travel(11)->minutes();
    expect($this->get('/stats')->viewData('stats')->first()['home'])->toBe(9);
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
    Redis::shouldReceive('get', 'set', 'hget', 'hgetall', 'connection')->andThrow(new RuntimeException('Redis down'));

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
    $stats->getPopularPages();
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
        config(['database.redis.options.prefix' => 'trolasi_database_']);
        Redis::purge();
        seedLegacy();
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
