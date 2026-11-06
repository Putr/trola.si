<?php

use App\Models\Station;
use App\Services\StatsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Redis;

function statsKey(CarbonInterface $date, string $page): string
{
    return 'stats_'.$date->format('Y_m_d').'_'.$page;
}

it('aggregates a day with totals, difference from the weekday average and year-over-year', function () {
    $this->travelTo('2026-11-04 12:00'); // a Wednesday
    $today = now();
    Redis::set(statsKey($today, 'home'), 30);
    Redis::set(statsKey($today, 'search'), 10);
    Redis::set(statsKey($today, 'station_600011'), 7);
    Redis::set(statsKey($today, 'station_600011_all'), 3);
    Redis::set(statsKey($today->copy()->subWeek(), 'home'), 10);
    Redis::set(statsKey($today->copy()->subYear(), 'home'), 15);

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
    Redis::set("stats_{$date}_station_600011", 4);
    Redis::set("stats_{$date}_station_600012_all", 2);

    expect(app(StatsService::class)->getStationViewsForDate($date))->toBe(6);

    Redis::flushdb();
});

it('lists popular pages with station names and week-over-week change', function () {
    Station::factory()->create(['code' => '600011', 'name' => 'Bavarski dvor']);
    Redis::set(statsKey(now(), 'station_600011_all'), 8);
    Redis::set(statsKey(now()->subDays(8), 'station_600011_all'), 4);
    Redis::set(statsKey(now(), 'home'), 2);

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
    Redis::set(statsKey(now(), 'home'), 5);
    $this->get('/stats')->assertOk();

    Redis::set(statsKey(now(), 'home'), 9);
    expect($this->get('/stats')->viewData('stats')->first()['home'])->toBe(5);

    $this->travel(11)->minutes();
    expect($this->get('/stats')->viewData('stats')->first()['home'])->toBe(9);
});

it('recalculates past days with --force', function () {
    $yesterday = now()->subDay();
    Redis::set(statsKey($yesterday, 'home'), 1);
    $this->artisan('stats:aggregate --days=2')->assertSuccessful();

    Redis::set(statsKey($yesterday, 'home'), 6);
    $this->artisan('stats:aggregate --days=2 --force')->assertSuccessful();

    expect(app(StatsService::class)->getAggregatedDay($yesterday->format('Y_m_d'))['home'])->toBe(6);
});

it('fails the aggregate command when Redis is down', function () {
    Redis::shouldReceive('get', 'set', 'keys', 'scan', 'connection')->andThrow(new RuntimeException('Redis down'));

    $this->artisan('stats:aggregate --today-only')->assertFailed();
});

it('still renders pages when Redis is down', function () {
    Redis::shouldReceive('incr')->andThrow(new RuntimeException('Redis down'));

    $this->get('/navodila')->assertOk();
});
