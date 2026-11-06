<?php

use App\Models\Station;
use Illuminate\Support\Facades\Http;
use Tests\Support\LppFixtures;

/**
 * @param  array<string, array<int, array{route: string, eta: int, destination?: string}>>  $arrivalsByCode
 */
function fakeArrivals(array $arrivalsByCode): void
{
    Http::fake(function ($request) use ($arrivalsByCode) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return Http::response(LppFixtures::arrivals($arrivalsByCode[$query['station-code'] ?? ''] ?? []));
    });
}

/**
 * @return array{0: Station, 1: Station}
 */
function stationPair(): array
{
    $toCenter = Station::factory()->create(['code' => '600011', 'name' => 'Bavarski dvor', 'is_direction_to_center' => true]);
    $fromCenter = Station::factory()->create(['code' => '600012', 'name' => 'Bavarski dvor', 'is_direction_to_center' => false]);
    $toCenter->update(['opposite_station_id' => $fromCenter->id]);
    $fromCenter->update(['opposite_station_id' => $toCenter->id]);

    return [$toCenter->fresh(), $fromCenter->fresh()];
}

it('lists arrivals grouped per route and sorted numerically', function () {
    [$station] = stationPair();
    fakeArrivals(['600011' => [
        ['route' => '6', 'eta' => 12],
        ['route' => '11B', 'eta' => 4],
        ['route' => '6', 'eta' => 3],
        ['route' => '1', 'eta' => 15],
    ]]);

    $arrivals = $this->get('/600011')->assertOk()->viewData('arrivals');

    expect(array_column($arrivals, 'routeName'))->toBe(['1', '6', '11B'])
        ->and($arrivals[1]['etaMin'])->toBe(3)
        ->and($arrivals[1]['laterEtas'])->toBe([12])
        ->and($arrivals[0]['etaMin'])->toBeNull()
        ->and($arrivals[0]['laterEtas'])->toBe([15]);
});

it('points direction links to itself and its opposite station', function () {
    stationPair();
    fakeArrivals([]);

    $this->get('/600011')->assertOk()->assertViewHas('hrefs', [
        'to' => '/600011',
        'from' => '/600012',
        'all' => '/600011/all',
    ])->assertViewHas('directionToCenter', true);

    $this->get('/600012')->assertOk()->assertViewHas('hrefs', [
        'to' => '/600011',
        'from' => '/600012',
        'all' => '/600012/all',
    ])->assertViewHas('directionToCenter', false);
});

it('only enables its own direction when a station has no opposite', function () {
    Station::factory()->create(['code' => '700021', 'is_direction_to_center' => false]);
    fakeArrivals([]);

    $this->get('/700021')->assertOk()->assertViewHas('hrefs', [
        'to' => null,
        'from' => '/700021',
        'all' => '/700021/all',
    ]);
});

it('merges arrivals of both stations on the both-directions page', function () {
    stationPair();
    fakeArrivals([
        '600011' => [['route' => '6', 'eta' => 2]],
        '600012' => [['route' => '1', 'eta' => 5]],
    ]);

    $response = $this->get('/600011/all')->assertOk()->assertViewHas('directionToCenter', null);

    expect(array_column($response->viewData('arrivals'), 'routeName'))->toBe(['1', '6']);
});

it('shows the not-found view for an unknown station', function () {
    $this->get('/123456')->assertViewIs('station-not-found');
});

it('shows a bus arriving now as 0 minutes', function () {
    stationPair();
    fakeArrivals(['600011' => [['route' => '6', 'eta' => 0]]]);

    $this->get('/600011')->assertOk()->assertSee("0'", false);
});
