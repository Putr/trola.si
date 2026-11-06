<?php

use App\Models\Station;

beforeEach(function () {
    Station::factory()->create(['code' => '600011', 'name' => 'Bavarski dvor', 'is_direction_to_center' => true]);
    Station::factory()->create(['code' => '600012', 'name' => 'Bavarski dvor', 'is_direction_to_center' => false]);
    Station::factory()->create(['code' => '803211', 'name' => 'Bavarska', 'is_direction_to_center' => true]);
    Station::factory()->create(['code' => '500011', 'name' => 'Kolodvor', 'is_direction_to_center' => true]);
});

it('ranks an exact name match first', function () {
    Station::factory()->create(['code' => '803212', 'name' => 'Bavarska ulica']);

    $stations = $this->get('/search?q=Bavarska&direction=all')->assertOk()->viewData('stations');

    expect($stations->pluck('code')->all())->toBe(['803211', '803212']);
});

it('finds a station by code prefix', function () {
    $this->get('/search?q=8032&direction=to')->assertRedirect('/803211');
});

it('redirects a single result to both directions when direction is all', function () {
    $this->get('/search?q=Kolodvor&direction=all')->assertRedirect('/500011/all');
});

it('filters results by direction', function () {
    $stations = $this->get('/search?q=Bavarsk&direction=to')->assertOk()->viewData('stations');

    expect($stations->pluck('code')->sort()->values()->all())->toBe(['600011', '803211']);
    $this->get('/search?q=Bavarsk&direction=from')->assertRedirect('/600012');
});

it('lists up to ten stations filtered by direction for an empty query', function () {
    Station::factory()->count(15)->create(['is_direction_to_center' => true]);

    $stations = $this->get('/search?direction=to')->assertOk()->viewData('stations');

    expect($stations)->toHaveCount(10)
        ->and($stations->every(fn (Station $s) => $s->is_direction_to_center === true))->toBeTrue();
});
