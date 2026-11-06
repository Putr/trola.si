<?php

use App\Models\Station;
use App\Services\LppApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\LppFixtures;

it('serves a repeated call within 30 seconds from cache', function () {
    Http::fake(['*' => Http::response(LppFixtures::arrivals([['route' => '6', 'eta' => 3]]))]);
    $api = app(LppApiService::class);

    $api->getStationArrivals('600011');
    $api->getStationArrivals('600011');

    Http::assertSentCount(1);
});

it('fetches again once the cache expires', function () {
    Http::fake(['*' => Http::response(LppFixtures::arrivals([['route' => '6', 'eta' => 3]]))]);
    $api = app(LppApiService::class);

    $api->getStationArrivals('600011');
    $this->travel(31)->seconds();
    $api->getStationArrivals('600011');

    Http::assertSentCount(2);
});

it('returns null and caches nothing when the API fails', function () {
    Http::fakeSequence()
        ->push('error', 500)
        ->push(LppFixtures::arrivals([['route' => '6', 'eta' => 3]]));
    $api = app(LppApiService::class);

    expect($api->getStationArrivals('600011'))->toBeNull()
        ->and($api->getStationArrivals('600011'))->toHaveKey('arrivals');
});

it('sends the bearer token and url-encoded query parameters', function () {
    Http::fake(['*' => Http::response(LppFixtures::stationsInRange([]))]);

    app(LppApiService::class)->getStationsInRange(46.05, 14.5);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-key')
        && str_starts_with($request->url(), 'https://lpp.test/api/station/stations-in-range?')
        && $request['latitude'] == 46.05
        && $request['radius'] == 500);
});

it('pings the API successfully', function () {
    Http::fake(['*' => Http::response(LppFixtures::arrivals([]))]);

    $this->artisan('app:lpp:ping')->assertSuccessful();
});

it('renders a station page without arrivals when the API is down', function () {
    Station::factory()->create(['code' => '600011']);
    Http::fake(['*' => Http::response('down', 500)]);

    $this->get('/600011')->assertOk()->assertSee('Ni napovedanih avtobusov');
});

it('gives the full station download a longer timeout than page requests', function () {
    $timeouts = [];
    Http::fake(function ($request, array $options) use (&$timeouts) {
        $timeouts[parse_url($request->url(), PHP_URL_PATH)] = $options['timeout'];

        return Http::response(['success' => true, 'data' => []]);
    });

    $api = app(LppApiService::class);
    $api->getAllStations();
    $api->getStationArrivals('600011');

    expect($timeouts['/api/station/station-details'])->toBeGreaterThanOrEqual(60)
        ->and($timeouts['/api/station/arrival'])->toBe(5);
});
