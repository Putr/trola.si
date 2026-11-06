<?php

use App\Models\Station;
use Database\Seeders\LoadStations;
use Illuminate\Support\Facades\Http;
use Tests\Support\LppFixtures;

/**
 * @param  array<int, array{id: int, code: string, name: string}>  $rows
 */
function fakeAllStations(array $rows): void
{
    Http::fake(['*station-details*' => Http::response(LppFixtures::allStations($rows))]);
}

it('pairs same-name stations and treats the code ending in 1 as toward the centre', function () {
    fakeAllStations([
        ['id' => 1, 'code' => '600012', 'name' => 'Bavarski dvor'],
        ['id' => 2, 'code' => '600011', 'name' => 'Bavarski dvor'],
    ]);

    $this->seed(LoadStations::class);

    expect(Station::find(2))->opposite_station_id->toBe(1)->is_direction_to_center->toBeTrue()
        ->and(Station::find(1))->opposite_station_id->toBe(2)->is_direction_to_center->toBeFalse();
});

it('pairs sequential codes when names differ', function () {
    fakeAllStations([
        ['id' => 1, 'code' => '700101', 'name' => 'Tbilisijska'],
        ['id' => 2, 'code' => '700102', 'name' => 'Tbilisijska 2'],
    ]);

    $this->seed(LoadStations::class);

    expect(Station::find(1))->opposite_station_id->toBe(2)->is_direction_to_center->toBeTrue()
        ->and(Station::find(2))->opposite_station_id->toBe(1)->is_direction_to_center->toBeFalse();
});

it('leaves an unpaired station without a direction', function () {
    fakeAllStations([
        ['id' => 1, 'code' => '700101', 'name' => 'Samotna'],
        ['id' => 2, 'code' => '900555', 'name' => 'Druga'],
    ]);

    $this->seed(LoadStations::class);

    expect(Station::find(1))->opposite_station_id->toBeNull()->is_direction_to_center->toBeNull();
});
