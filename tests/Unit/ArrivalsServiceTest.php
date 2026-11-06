<?php

use App\Models\Station;
use App\Services\ArrivalsService;
use Illuminate\Support\Facades\Http;
use Tests\Support\LppFixtures;

/**
 * @param  array<int, array{route: string, eta: int}>  $rows
 * @return array<int, array<string, mixed>>
 */
function arrivalsFor(array $rows): array
{
    Http::fake(['*' => Http::response(LppFixtures::arrivals($rows))]);

    return app(ArrivalsService::class)->forStation(new Station(['code' => '600011']));
}

it('groups arrivals per route and sorts routes numerically', function () {
    $arrivals = arrivalsFor([
        ['route' => '11B', 'eta' => 4],
        ['route' => '6', 'eta' => 12],
        ['route' => '6', 'eta' => 3],
        ['route' => '1', 'eta' => 15],
    ]);

    expect(array_column($arrivals, 'routeName'))->toBe(['1', '6', '11B'])
        ->and($arrivals[1]['etas'])->toBe([3, 12]);
});

it('highlights the first arrival only when it is at most nine minutes away', function () {
    $arrivals = arrivalsFor([
        ['route' => '1', 'eta' => 9],
        ['route' => '1', 'eta' => 20],
        ['route' => '2', 'eta' => 10],
    ]);

    expect($arrivals[0])->etaMin->toBe(9)->laterEtas->toBe([20])
        ->and($arrivals[1])->etaMin->toBeNull()->laterEtas->toBe([10]);
});

it('keeps a bus arriving now as zero minutes', function () {
    expect(arrivalsFor([['route' => '1', 'eta' => 0]])[0]['etaMin'])->toBe(0);
});

it('returns no arrivals when the API fails', function () {
    Http::fake(['*' => Http::response('down', 500)]);

    expect(app(ArrivalsService::class)->forStation(new Station(['code' => '600011'])))->toBe([]);
});
