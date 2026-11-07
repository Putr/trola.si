<?php

use App\Models\Station;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * 28 days at 100 views, then 28 days at 110: an average of 110 and +10 % on the 28 days before.
 */
function statsPageSeed(): void
{
    test()->travelTo('2026-12-31 12:00');
    config(['stats.filters_live_since' => '2026-11-07']);

    Station::factory()->create(['code' => '600011', 'name' => 'Konzorcij', 'is_direction_to_center' => true]);
    Station::factory()->create(['code' => '600012', 'name' => 'Konzorcij', 'is_direction_to_center' => false]);
    Station::factory()->create(['code' => '600021', 'name' => 'Šiška']);
    Station::factory()->create(['code' => '600031', 'name' => 'Vič']);

    for ($day = now()->parse('2026-11-05'); $day->lte(now()->parse('2026-12-30')); $day->addDay()) {
        $current = $day->gte(now()->parse('2026-12-03'));
        seedAgg($day->format('Y_m_d'), $current ? 110 : 100, 0);
        seedStationViews($day->format('Y_m_d'), [
            'station_600011' => $current ? 20 : 10,
            'station_600021' => $current ? 80 : 90,
        ]);
    }
}

it('shows the four sections with the average and its change', function () {
    statsPageSeed();

    $this->get('/stats')->assertOk()
        ->assertSee('Ali se uporablja?')
        ->assertSee('Trend')
        ->assertSee('Postaje')
        ->assertSee('Poišči postajo')
        ->assertSee('110')
        ->assertSee('+10 %');
});

it('lists the rising group first', function () {
    statsPageSeed();

    $this->get('/stats?seznam=rising')->assertOk()
        ->assertSeeInOrder(['Konzorcij', '+100 %']);
});

it('lists the falling group', function () {
    statsPageSeed();

    $this->get('/stats?seznam=falling')->assertOk()->assertSee('Šiška')->assertSee('−11 %');
});

it('shows the unused count on the least used list', function () {
    statsPageSeed();

    $this->get('/stats?seznam=least')->assertOk()->assertSee('1 postaja brez ogledov');
});

it('renders the 12 month range', function () {
    statsPageSeed();

    $this->get('/stats?obdobje=12')->assertOk()->assertSee('12 m');
});

it('falls back to defaults for junk input', function () {
    statsPageSeed();

    foreach (['?obdobje=abc', '?seznam[]=x', '?seznam=nope&obdobje[]=3', '?postaja[]=x', '?obdobje=99999999999999999999'] as $query) {
        $this->get('/stats'.$query)->assertOk()->assertSee('Ali se uporablja?');
    }
});

it('looks up a station group by name with its rank', function () {
    statsPageSeed();

    $this->get('/stats?postaja=konzorcij')->assertOk()
        ->assertSee('Konzorcij')
        ->assertSee('proti centru')
        ->assertSee('iz centra')
        ->assertSee('mesto #2 od 3');
});

it('says so when a station is not found', function () {
    statsPageSeed();

    $this->get('/stats?postaja=zzz')->assertOk()->assertSee('Postaja ni najdena');
});

it('escapes the looked up term', function () {
    statsPageSeed();

    $this->get('/stats?postaja='.urlencode('<script>alert(1)</script>'))->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('renders aggregates without data as "ni podatkov" instead of failing', function () {
    $this->get('/stats')->assertOk()->assertSee('ni podatkov');
});

it('does not count visits to the stats page', function () {
    statsPageSeed();

    $this->get('/stats')->assertOk();
    $this->get('/stats?postaja=konzorcij')->assertOk();

    expect(Redis::exists('stats_day_'.now()->format('Y_m_d')))->toBe(0);
});

it('caches the page data for ten minutes', function () {
    statsPageSeed();
    $this->get('/stats')->assertOk();

    seedAgg('2026_12_30', 894, 0); // lifts the 28-day average from 110 to 138
    $this->get('/stats')->assertOk();
    expect(Cache::has('stats_page_v2'))->toBeTrue();
    $this->get('/stats')->assertDontSee('138');

    $this->travel(11)->minutes();
    $this->get('/stats')->assertOk()->assertSee('138');
});

it('still renders when Redis is down', function () {
    Redis::shouldReceive('mget', 'connection', 'get', 'hget', 'hgetall', 'set')->andThrow(new RuntimeException('Redis down'));

    $this->get('/stats')->assertOk()->assertSee('Statistika trenutno ni na voljo');
});
