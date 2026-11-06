<?php

use App\Models\Station;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Station::factory()->create(['code' => '600011', 'name' => 'Bavarski dvor', 'is_direction_to_center' => true]);
    Station::factory()->create(['code' => '600012', 'name' => 'Bavarski dvor', 'is_direction_to_center' => false]);
});

it('does not fail on search input with boolean-mode operators or wildcards', function (string $query) {
    $status = $this->get('/search?'.http_build_query(['q' => $query, 'direction' => 'all']))->status();

    expect($status)->toBeIn([200, 302]);
})->with(['*', '+', '"', '@a', '-', '~', '()', '%', '_', '\\', 'Bavar"ski*']);

it('treats LIKE wildcards literally', function () {
    $this->get('/search?q=%25&direction=all')->assertOk()->assertViewHas('stations', fn ($stations) => $stations->isEmpty());
});

it('keeps a query with & intact in the direction links', function () {
    $hrefs = $this->get('/search?q=a%26b%23c&direction=all')->assertOk()->viewData('hrefs');

    expect($hrefs['to'])->toBe(route('search', ['q' => 'a&b#c', 'direction' => 'to'], false));
});

it('treats an unknown direction as all', function () {
    $this->get('/search?direction=sideways')->assertOk()->assertViewHas('direction', 'all');
});

it('redirects invalid coordinates to manual search', function (string $query) {
    Http::fake();

    $this->get('/geosearch?'.$query)->assertRedirect(route('search', ['locationFailed' => 1]));
    Http::assertNothingSent();
})->with([
    'missing' => [''],
    'non-numeric' => ['lat=abc&lon=def'],
    'null island' => ['lat=0&lon=0'],
    'out of range' => ['lat=91&lon=200'],
    'array' => ['lat[]=1&lon=14.5'],
]);

it('returns 404 with the not-found page for an unknown numeric code', function () {
    $this->get('/123456')->assertNotFound()->assertViewIs('station-not-found');
    $this->get('/123456/all')->assertNotFound()->assertViewIs('station-not-found');
});

it('returns 404 for a non-numeric station code', function () {
    $this->get('/wp-login.php')->assertNotFound();
    $this->get('/abc/all')->assertNotFound();
});

it('does not create stats keys for unknown stations', function () {
    $this->get('/999999/all');
    $this->get('/999998');

    expect(Redis::keys('*station_99999*'))->toBe([]);
});

it('names every public route', function (string $name) {
    expect(Route::has($name))->toBeTrue();
})->with(['index', 'search', 'geosearch', 'help', 'stats', 'station', 'station.both-directions']);
