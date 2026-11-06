<?php

use Illuminate\Support\Facades\Redis;

function todayKey(string $page): string
{
    return 'stats_'.now()->format('Y_m_d').'_'.$page;
}

it('renders the home page and counts the view', function () {
    $this->get('/')->assertOk();

    expect((int) Redis::get(todayKey('home')))->toBe(1);
});

it('renders the help page and counts the view', function () {
    $this->get('/navodila')->assertOk();

    expect((int) Redis::get(todayKey('help')))->toBe(1);
});

it('does not count auto-reloads', function () {
    $this->withUnencryptedCookie('autoreload', '1')->get('/')->assertOk();

    expect(Redis::get(todayKey('home')))->toBeNull();
});
