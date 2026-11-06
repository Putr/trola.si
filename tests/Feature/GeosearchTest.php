<?php

use App\Models\Station;
use Illuminate\Support\Facades\Http;
use Tests\Support\LppFixtures;

it('lists nearby stations in API order with distance', function () {
    Station::factory()->create(['code' => '600011', 'name' => 'Bavarski dvor']);
    Station::factory()->create(['code' => '600012', 'name' => 'Bavarski dvor']);
    Http::fake(['*stations-in-range*' => Http::response(LppFixtures::stationsInRange([
        ['code' => '600012', 'distance' => 40.4],
        ['code' => '600011', 'distance' => 120.6],
    ]))]);

    $stations = $this->get('/geosearch?lat=46.05&lon=14.50')->assertOk()->viewData('stations');

    expect($stations->pluck('code')->values()->all())->toBe(['600012', '600011'])
        ->and($stations->pluck('distance')->values()->all())->toEqual([40, 121]);
});

it('redirects a single nearby station to both directions', function () {
    Station::factory()->create(['code' => '600011']);
    Http::fake(['*stations-in-range*' => Http::response(LppFixtures::stationsInRange([
        ['code' => '600011', 'distance' => 10],
    ]))]);

    $this->get('/geosearch?lat=46.05&lon=14.50')->assertRedirect('/600011/all');
});

it('shows a Slovenian error when no stations are nearby', function () {
    Http::fake(['*stations-in-range*' => Http::response(LppFixtures::stationsInRange([]))]);

    $this->get('/geosearch?lat=46.05&lon=14.50')->assertOk()->assertSee('V bližini ni najdenih postaj.');
});
