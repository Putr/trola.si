<?php

use App\Services\Stats\BotDetector;

const WEBVIEW_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 7 Build/UQ1A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/129.0.0.0 Mobile Safari/537.36 TrolaAndroidApp';
const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';
const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

it('flags self-announced bots', function (?string $userAgent) {
    expect((new BotDetector)->isBot($userAgent))->toBeTrue();
})->with([
    'null' => [null],
    'empty' => [''],
    'googlebot' => ['Googlebot/2.1'],
    'bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0)'],
    'facebook' => ['facebookexternalhit/1.1'],
    'whatsapp' => ['WhatsApp/2.23'],
    'curl' => ['curl/8.4.0'],
    'python' => ['python-requests/2.31'],
    'go' => ['Go-http-client/1.1'],
    'headless' => ['HeadlessChrome/120'],
    'uptime' => ['UptimeRobot/2.0'],
    'slack' => ['Slackbot-LinkExpanding'],
]);

it('does not flag real browsers or the Android app', function (string $userAgent) {
    expect((new BotDetector)->isBot($userAgent))->toBeFalse();
})->with([
    'iphone' => [IPHONE_UA],
    'desktop' => [DESKTOP_UA],
    'android webview' => [WEBVIEW_UA],
]);

it('recognises the Android app only by its marker', function () {
    $detector = new BotDetector;

    expect($detector->isApp(WEBVIEW_UA))->toBeTrue()
        ->and($detector->isApp(IPHONE_UA))->toBeFalse()
        ->and($detector->isApp(DESKTOP_UA))->toBeFalse()
        ->and($detector->isApp(null))->toBeFalse();
});
