<?php

use App\Actions\CompileIpRanges;
use App\Services\Stats\IpClassifier;
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

/**
 * Compile the IP fixture to a temp file and point the classifier at it.
 * Fixture addresses: 93.103.1.1 (SI ISP), 88.198.0.1 (Hetzner), 73.1.1.1 (Comcast US).
 */
function useIpFixture(): string
{
    $path = sys_get_temp_dir().'/trola-ipdata-'.bin2hex(random_bytes(4)).'.php';
    (new CompileIpRanges)->fromTsv(base_path('tests/fixtures/ip2asn-sample.tsv'), $path);
    pointClassifierAt($path);

    return $path;
}

/**
 * Point the IP classifier at a data file (which may not exist) and rebuild the singleton.
 */
function pointClassifierAt(string $path): void
{
    config(['stats.ip_data_path' => $path]);
    app()->forgetInstance(IpClassifier::class);
}
