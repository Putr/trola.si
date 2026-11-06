<?php

use App\Models\Station;
use Illuminate\Support\Facades\Redis;

it('shows daily totals and popular pages', function () {
    Station::factory()->create(['code' => '600011', 'name' => 'Bavarski dvor']);
    $today = now()->format('Y_m_d');
    Redis::hset("stats_day_{$today}", 'home', 5);
    Redis::hset("stats_day_{$today}", 'station_600011', 3);

    $response = $this->get('/stats')->assertOk();

    $todayStats = $response->viewData('stats')->first();
    expect($todayStats['home'])->toBe(5)
        ->and($todayStats['stations'])->toBe(3);
    $response->assertSee('Bavarski dvor');
});
