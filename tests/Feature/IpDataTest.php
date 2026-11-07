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
    'cloudflare is foreign, not datacenter' => ['104.16.5.5', IpClass::Foreign],
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

    // 7 fixture ranges plus 6 gaps would be 13 unmerged records; same-class neighbours collapse.
    expect($counts['v4'])->toBe(8)
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
    Http::fake(['*' => Http::response(gzencode((string) file_get_contents(base_path('tests/fixtures/ip2asn-sample.tsv'))))]);

    $this->artisan('stats:update-ip-data')->assertSuccessful();

    pointClassifierAt($path);
    expect(file_exists($path))->toBeTrue()
        ->and(classify('88.198.0.1'))->toBe(IpClass::Datacenter)
        ->and(classify('93.103.1.1'))->toBe(IpClass::Counted);
});
