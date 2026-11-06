<?php

use App\Models\Station;
use App\Repositories\StationRepository;
use Database\Seeders\LoadStations;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
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

it('never exposes a null pairing for a station that stays paired', function () {
    fakeAllStations([
        ['id' => 1, 'code' => '600011', 'name' => 'Bavarski dvor'],
        ['id' => 2, 'code' => '600012', 'name' => 'Bavarski dvor'],
    ]);
    $this->seed(LoadStations::class);

    $baseLevel = DB::transactionLevel();
    $violations = [];
    Station::saving(function (Station $station) use (&$violations, $baseLevel) {
        if ($station->opposite_station_id === null || DB::transactionLevel() <= $baseLevel) {
            $violations[] = $station->code;
        }
    });

    $this->seed(LoadStations::class);

    expect($violations)->toBe([]);
});

it('refreshes cached stations by code and by id after a sync', function () {
    Http::fakeSequence('*station-details*')
        ->push(LppFixtures::allStations([
            ['id' => 1, 'code' => '600011', 'name' => 'Bavarski dvor'],
            ['id' => 2, 'code' => '600012', 'name' => 'Bavarski dvor'],
        ]))
        ->push(LppFixtures::allStations([
            ['id' => 1, 'code' => '600011', 'name' => 'Bavarski dvor'],
            ['id' => 2, 'code' => '600013', 'name' => 'Bavarski dvor'],
        ]));
    Http::fake(['*arrival*' => Http::response(LppFixtures::arrivals([]))]);

    $this->seed(LoadStations::class);
    $this->get('/600011')->assertViewHas('hrefs', fn (array $hrefs) => $hrefs['from'] === '/600012');

    $this->seed(LoadStations::class);
    $this->get('/600011')->assertViewHas('hrefs', fn (array $hrefs) => $hrefs['from'] === '/600013');
});

it('keeps existing pairings and fails when the API is down', function () {
    $a = Station::factory()->create(['id' => 1, 'code' => '600011']);
    Station::factory()->create(['id' => 2, 'code' => '600012', 'opposite_station_id' => 1]);
    $a->update(['opposite_station_id' => 2]);
    Http::fake(['*station-details*' => Http::response('down', 503)]);

    $this->artisan('stations:sync')->assertFailed();

    expect(Station::find(1)->opposite_station_id)->toBe(2);
});

it('pairs only unpaired stations when three share a name', function () {
    fakeAllStations([
        ['id' => 1, 'code' => '600011', 'name' => 'Center'],
        ['id' => 2, 'code' => '600012', 'name' => 'Center'],
        ['id' => 3, 'code' => '600099', 'name' => 'Center'],
    ]);

    $this->seed(LoadStations::class);

    expect(Station::find(1)->opposite_station_id)->toBe(2)
        ->and(Station::find(2)->opposite_station_id)->toBe(1)
        ->and(Station::find(3)->opposite_station_id)->toBeNull();
});

it('schedules the station sync instead of db:seed', function () {
    $commands = collect(app(Schedule::class)->events())->map->command->implode("\n");

    expect($commands)->toContain('stations:sync')->not->toContain('db:seed');
});

it('warms the cache for every station', function () {
    $station = Station::factory()->create(['code' => '600011', 'name' => 'Staro ime']);
    app(StationRepository::class)->findByCode('600011');
    $station->update(['name' => 'Novo ime']);

    $this->artisan('stations:warm-cache')->assertSuccessful();

    expect(app(StationRepository::class)->findByCode('600011')->name)->toBe('Novo ime');
});

it('pairs names that differ only in case, accents or trailing spaces, as MySQL did before', function () {
    fakeAllStations([
        ['id' => 1, 'code' => '800011', 'name' => 'Šmartinska'],
        ['id' => 2, 'code' => '800015', 'name' => 'smartinska '],
    ]);

    $this->seed(LoadStations::class);

    expect(Station::find(1))->opposite_station_id->toBe(2)->is_direction_to_center->toBeTrue()
        ->and(Station::find(2))->opposite_station_id->toBe(1);
});
