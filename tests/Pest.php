<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Never talk to the live LPP API, and start every test with an empty test Redis.
 * The port guard makes sure we can only ever flush the throwaway test container.
 */
function isolateExternalServices(): void
{
    Http::preventStrayRequests();

    if ((int) config('database.redis.default.port') !== 63379) {
        throw new RuntimeException('Refusing to flush Redis: tests must run against the test container (port 63379).');
    }

    Redis::flushdb();
}

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => isolateExternalServices())
    ->in('Feature');

pest()->extend(TestCase::class)
    ->beforeEach(fn () => isolateExternalServices())
    ->in('Unit');
