<?php

use Illuminate\Support\Facades\Redis;

function todayViews(string $page): ?string
{
    $views = Redis::hget('stats_day_'.now()->format('Y_m_d'), $page);

    return $views === false ? null : $views;
}

it('renders the home page and counts the view', function () {
    $this->get('/')->assertOk();

    expect((int) todayViews('home'))->toBe(1);
});

it('renders the help page and counts the view', function () {
    $this->get('/navodila')->assertOk();

    expect((int) todayViews('help'))->toBe(1);
});

it('does not count auto-reloads', function () {
    $this->withUnencryptedCookie('autoreload', '1')->get('/')->assertOk();

    expect(todayViews('home'))->toBeNull();
});
