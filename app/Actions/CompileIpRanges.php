<?php

namespace App\Actions;

use App\Enums\IpClass;
use RuntimeException;

/**
 * Compiles an iptoasn "ip2asn-combined" TSV into a PHP file holding two packed strings
 * (IPv4 and IPv6) that opcache keeps in memory and IpClassifier binary-searches.
 *
 * Each record is the big-endian start address (4 or 16 bytes) followed by one class byte.
 * A record applies until the next record's start. Gaps between listed ranges become
 * Counted records, and consecutive ranges of the same class are merged.
 */
class CompileIpRanges
{
    /**
     * @return array{v4: int, v6: int} the number of merged ranges written per family
     */
    public function fromTsv(string $tsvPath, string $outputPath): array
    {
        $handle = fopen($tsvPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot read {$tsvPath}");
        }

        /** @var array<int, array{data: string, count: int, class: IpClass|null, end: string|null}> $families */
        $families = [
            4 => ['data' => '', 'count' => 0, 'class' => null, 'end' => null],
            16 => ['data' => '', 'count' => 0, 'class' => null, 'end' => null],
        ];

        try {
            while (($line = fgets($handle)) !== false) {
                $fields = explode("\t", rtrim($line, "\r\n"), 5);
                if (count($fields) < 5) {
                    continue;
                }

                [$start, $end, $asn, $country, $description] = $fields;
                $startBytes = filter_var($start, FILTER_VALIDATE_IP) ? inet_pton($start) : false;
                $endBytes = filter_var($end, FILTER_VALIDATE_IP) ? inet_pton($end) : false;
                if ($startBytes === false || $endBytes === false || strlen($startBytes) !== strlen($endBytes)) {
                    continue;
                }

                $family = &$families[strlen($startBytes)];
                $class = $this->classify((int) $asn, $country, $description);

                if ($family['end'] !== null) {
                    $expected = $this->increment($family['end']);
                    if ($expected !== null && strcmp($startBytes, $expected) > 0) {
                        $this->push($family, $expected, IpClass::Counted);
                    }
                }

                $this->push($family, $startBytes, $class);
                $family['end'] = $endBytes;
                unset($family);
            }
        } finally {
            fclose($handle);
        }

        $php = "<?php return ['v4' => '".addcslashes($families[4]['data'], "\\'")
            ."', 'v6' => '".addcslashes($families[16]['data'], "\\'")."'];\n";

        $directory = dirname($outputPath);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }
        if (file_put_contents($outputPath, $php) === false) {
            throw new RuntimeException("Cannot write {$outputPath}");
        }

        return ['v4' => $families[4]['count'], 'v6' => $families[16]['count']];
    }

    /**
     * @param  array{data: string, count: int, class: IpClass|null, end: string|null}  $family
     */
    private function push(array &$family, string $start, IpClass $class): void
    {
        if ($family['class'] === $class) {
            return;
        }

        $family['data'] .= $start.chr($class->byte());
        $family['count']++;
        $family['class'] = $class;
    }

    private function classify(int $asn, string $country, string $description): IpClass
    {
        if ($asn === 0) {
            return IpClass::Counted;
        }

        $description = strtolower($description);

        if ($this->containsAny($description, (array) config('stats.datacenter_keywords'))
            && ! $this->containsAny($description, (array) config('stats.cdn_exclusions'))) {
            return IpClass::Datacenter;
        }

        return in_array(strtoupper($country), (array) config('stats.europe'), true)
            ? IpClass::Counted
            : IpClass::Foreign;
    }

    /**
     * @param  array<int, mixed>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The address after $bytes, or null when it is the last address of its family.
     */
    private function increment(string $bytes): ?string
    {
        for ($i = strlen($bytes) - 1; $i >= 0; $i--) {
            if ($bytes[$i] !== "\xff") {
                $bytes[$i] = chr(ord($bytes[$i]) + 1);

                return $bytes;
            }
            $bytes[$i] = "\x00";
        }

        return null;
    }
}
