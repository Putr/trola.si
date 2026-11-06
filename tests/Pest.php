<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit');

/*
 * Never talk to the live LPP API, and start every test with an empty test Redis.
 * The port guard makes sure we can only ever flush the throwaway test container.
 */
beforeEach(function () {
    Http::preventStrayRequests();

    if ((int) config('database.redis.default.port') !== 63379) {
        throw new RuntimeException('Refusing to flush Redis: tests must run against the test container (port 63379).');
    }

    Redis::flushdb();
});
