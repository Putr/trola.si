<?php

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

const FILTER_SAFARI_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';
const FILTER_WEBVIEW_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 7 Build/UQ1A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/129.0.0.0 Mobile Safari/537.36 TrolaAndroidApp';

/**
 * @return array<string, string>
 */
function todayHash(): array
{
    return Redis::hgetall('stats_day_'.now()->format('Y_m_d')) ?: [];
}

/**
 * Request the home page as a visitor behind the trusted Docker proxy.
 */
function visitHome(TestCase $test, string $ip, string $userAgent = FILTER_SAFARI_UA): void
{
    $test->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
        ->withHeaders(['X-Forwarded-For' => $ip, 'User-Agent' => $userAgent])
        ->get('/')
        ->assertOk();
}

beforeEach(fn () => useIpFixture());

it('counts a reload only as filtered', function () {
    $this->withUnencryptedCookie('autoreload', '1')->get('/')->assertOk();

    expect(todayHash())->toBe(['filtered:reload' => '1']);
});

it('counts a bot only as filtered', function () {
    visitHome($this, '93.103.1.1', 'Googlebot/2.1');

    expect(todayHash())->toBe(['filtered:bot' => '1']);
});

it('counts a datacenter address only as filtered', function () {
    visitHome($this, '88.198.0.1');

    expect(todayHash())->toBe(['filtered:datacenter' => '1']);
});

it('counts a foreign address only as filtered', function () {
    visitHome($this, '73.1.1.1');

    expect(todayHash())->toBe(['filtered:foreign' => '1']);
});

it('counts a human web view', function () {
    visitHome($this, '93.103.1.1');

    expect(todayHash())->toBe(['home' => '1']);
});

it('counts a human app view on the page and as an app view', function () {
    visitHome($this, '93.103.1.1', FILTER_WEBVIEW_UA);

    expect(todayHash())->toBe(['home' => '1', 'app' => '1']);
});

it('applies the filters in order: reload, bot, then address', function () {
    visitHome($this, '88.198.0.1', 'Googlebot/2.1');

    expect(todayHash())->toBe(['filtered:bot' => '1']);
});

it('does not believe a spoofed X-Forwarded-For from the internet', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '88.198.0.1'])
        ->withHeaders(['X-Forwarded-For' => '93.103.1.1', 'User-Agent' => FILTER_SAFARI_UA])
        ->get('/')
        ->assertOk();

    expect(todayHash())->toBe(['filtered:datacenter' => '1']);
});

it('counts everything when there is no IP data', function () {
    pointClassifierAt('/nonexistent/ranges.php');

    visitHome($this, '88.198.0.1');

    expect(todayHash())->toBe(['home' => '1']);
});
