<?php

use App\Actions\CompileIpRanges;
use App\Enums\IpClass;
use App\Services\Stats\IpClassifier;
use Illuminate\Support\Facades\Http;

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/trola-ipdata-*') ?: [] as $file) {
        unlink($file);
    }
});

function classify(string $ip): IpClass
{
    return app(IpClassifier::class)->classify($ip);
}

it('classifies addresses from the compiled data', function (string $ip, IpClass $expected) {
    useIpFixture();

    expect(classify($ip))->toBe($expected);
})->with([
    'slovenian isp' => ['93.103.1.1', IpClass::Counted],
    'second adjacent slovenian range' => ['93.103.200.9', IpClass::Counted],
    'hetzner' => ['88.198.0.1', IpClass::Datacenter],
    'comcast' => ['73.1.1.1', IpClass::Foreign],
    'cloudflare is counted outright, not foreign or datacenter' => ['104.16.5.5', IpClass::Counted],
    'not routed' => ['120.0.0.9', IpClass::Counted],
    'ipv6 slovenian isp' => ['2a00:ee0:1234::1', IpClass::Counted],
    'ipv6 amazon' => ['2600:1f18::1', IpClass::Datacenter],
    'ipv4 amazon' => ['52.0.0.1', IpClass::Datacenter],
    'ipv4-mapped ipv6 hetzner' => ['::ffff:88.198.0.1', IpClass::Datacenter],
    'in no range' => ['10.0.0.5', IpClass::Counted],
    'range boundary start' => ['88.198.0.0', IpClass::Datacenter],
    'range boundary end' => ['88.198.255.255', IpClass::Datacenter],
    'just after a range' => ['88.199.0.0', IpClass::Counted],
    'invalid string' => ['nope', IpClass::Counted],
]);

it('merges adjacent ranges of the same class', function () {
    $counts = (new CompileIpRanges)->fromTsv(
        base_path('tests/fixtures/ip2asn-sample.tsv'),
        sys_get_temp_dir().'/trola-ipdata-merge.php',
    );

    // Same-class neighbours (and Cloudflare, which now counts) collapse into their gaps.
    expect($counts['v4'])->toBe(6)
        ->and($counts['v6'])->toBe(2);
});

it('counts everything when the data file is missing or corrupt', function () {
    pointClassifierAt('/nonexistent/ranges.php');
    expect(classify('88.198.0.1'))->toBe(IpClass::Counted);

    $garbage = sys_get_temp_dir().'/trola-ipdata-garbage.php';
    file_put_contents($garbage, "<?php return 'garbage';");
    pointClassifierAt($garbage);
    expect(classify('88.198.0.1'))->toBe(IpClass::Counted);

    $broken = sys_get_temp_dir().'/trola-ipdata-broken.php';
    file_put_contents($broken, '<?php return [');
    pointClassifierAt($broken);
    expect(classify('88.198.0.1'))->toBe(IpClass::Counted);

    $badLength = sys_get_temp_dir().'/trola-ipdata-badlength.php';
    file_put_contents($badLength, "<?php return ['v4' => 'abc', 'v6' => ''];");
    pointClassifierAt($badLength);
    expect(classify('88.198.0.1'))->toBe(IpClass::Counted);
});

it('keeps the previous data when the download fails', function () {
    $path = useIpFixture();
    $before = file_get_contents($path);
    Http::fake(['*' => Http::response('', 500)]);

    $this->artisan('stats:update-ip-data')->assertFailed();

    expect(file_get_contents($path))->toBe($before)
        ->and(glob(dirname($path).'/.update-*'))->toBeEmpty();
});

it('keeps the previous data when the download is not valid gzip', function () {
    $path = useIpFixture();
    $before = file_get_contents($path);
    Http::fake(['*' => Http::response('not gzip at all', 200)]);

    $this->artisan('stats:update-ip-data')->assertFailed();

    expect(file_get_contents($path))->toBe($before);
});

it('downloads and installs fresh data', function () {
    $path = sys_get_temp_dir().'/trola-ipdata-fresh.php';
    pointClassifierAt($path);
    config(['stats.ip_data_min_v4' => 1, 'stats.ip_data_min_v6' => 1]);
    Http::fake(['*' => Http::response(gzencode((string) file_get_contents(base_path('tests/fixtures/ip2asn-sample.tsv'))))]);

    $this->artisan('stats:update-ip-data')->assertSuccessful();

    pointClassifierAt($path);
    expect(file_exists($path))->toBeTrue()
        ->and(classify('88.198.0.1'))->toBe(IpClass::Datacenter)
        ->and(classify('93.103.1.1'))->toBe(IpClass::Counted);
});

function compileTsv(string $tsv): array
{
    $source = sys_get_temp_dir().'/trola-ipdata-src-'.bin2hex(random_bytes(4)).'.tsv';
    file_put_contents($source, $tsv);
    $output = sys_get_temp_dir().'/trola-ipdata-out-'.bin2hex(random_bytes(4)).'.php';
    $counts = (new CompileIpRanges)->fromTsv($source, $output);
    pointClassifierAt($output);

    return $counts;
}

it('counts addresses above the last listed range', function () {
    compileTsv(
        "88.198.0.0\t88.198.255.255\t24940\tDE\tHETZNER-AS Hetzner Online GmbH\n"
        ."2600:1f00::\t2600:1fff:ffff:ffff:ffff:ffff:ffff:ffff\t16509\tUS\tAMAZON-02 - Amazon.com, Inc.\n"
    );

    expect(classify('88.198.1.1'))->toBe(IpClass::Datacenter)
        ->and(classify('88.199.0.0'))->toBe(IpClass::Counted)
        ->and(classify('200.1.1.1'))->toBe(IpClass::Counted)
        ->and(classify('2600:1f18::1'))->toBe(IpClass::Datacenter)
        ->and(classify('2700::1'))->toBe(IpClass::Counted);
});

it('refuses unsorted or overlapping input', function (string $tsv) {
    expect(fn () => compileTsv($tsv))->toThrow(RuntimeException::class);
})->with([
    'unsorted' => ["93.103.0.0\t93.103.255.255\t3212\tSI\tSIOL\n88.198.0.0\t88.198.255.255\t24940\tDE\tHETZNER\n"],
    'overlapping' => ["88.198.0.0\t88.198.255.255\t24940\tDE\tHETZNER\n88.198.255.0\t88.199.0.255\t24940\tDE\tHETZNER\n"],
]);

it('keeps the previous data when the download has too few ranges', function () {
    $path = useIpFixture();
    $before = file_get_contents($path);
    Http::fake(['*' => Http::response(gzencode((string) file_get_contents(base_path('tests/fixtures/ip2asn-sample.tsv'))))]);

    $this->artisan('stats:update-ip-data')->assertFailed();

    expect(file_get_contents($path))->toBe($before);
});

it('keeps the previous data when the download is unsorted', function () {
    $path = useIpFixture();
    $before = file_get_contents($path);
    config(['stats.ip_data_min_v4' => 1, 'stats.ip_data_min_v6' => 0]);
    Http::fake(['*' => Http::response(gzencode("93.103.0.0\t93.103.255.255\t3212\tSI\tSIOL\n88.198.0.0\t88.198.255.255\t24940\tDE\tHETZNER\n"))]);

    $this->artisan('stats:update-ip-data')->assertFailed();

    expect(file_get_contents($path))->toBe($before);
});

it('never echoes a data file that is not PHP', function () {
    $path = sys_get_temp_dir().'/trola-ipdata-text.php';
    file_put_contents($path, "secret contents <?php return ['v4' => '', 'v6' => ''];");
    pointClassifierAt($path);

    ob_start();
    $class = classify('88.198.0.1');
    $output = ob_get_clean();

    expect($output)->toBe('')
        ->and($class)->toBe(IpClass::Counted);
});
