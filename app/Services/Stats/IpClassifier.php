<?php

namespace App\Services\Stats;

use App\Enums\IpClass;
use Throwable;

/**
 * Classifies a visitor IP with the compiled iptoasn ranges (see CompileIpRanges).
 *
 * The address is only looked up in memory; it is never stored or logged. A missing or
 * corrupt data file means every address counts.
 */
class IpClassifier
{
    private const CLASS_BYTE = 1;

    /** @var array{v4: string, v6: string}|null */
    private ?array $ranges = null;

    public function __construct(private readonly string $dataPath) {}

    public function classify(string $ip): IpClass
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return IpClass::Counted;
        }

        $bytes = (string) inet_pton($ip);
        if (strlen($bytes) === 16 && str_starts_with($bytes, str_repeat("\x00", 10)."\xff\xff")) {
            $bytes = substr($bytes, 12);
        }

        $ranges = $this->ranges();

        return $this->lookup(strlen($bytes) === 4 ? $ranges['v4'] : $ranges['v6'], $bytes);
    }

    private function lookup(string $data, string $needle): IpClass
    {
        $keyWidth = strlen($needle);
        $width = $keyWidth + self::CLASS_BYTE;
        $low = 0;
        $high = intdiv(strlen($data), $width) - 1;
        $found = -1;

        while ($low <= $high) {
            $middle = ($low + $high) >> 1;
            if (strcmp(substr($data, $middle * $width, $keyWidth), $needle) <= 0) {
                $found = $middle;
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        return $found < 0
            ? IpClass::Counted
            : IpClass::fromByte(ord($data[$found * $width + $keyWidth]));
    }

    /**
     * @return array{v4: string, v6: string}
     */
    private function ranges(): array
    {
        return $this->ranges ??= $this->load();
    }

    /**
     * @return array{v4: string, v6: string}
     */
    private function load(): array
    {
        $empty = ['v4' => '', 'v6' => ''];

        if (! is_file($this->dataPath)) {
            return $empty;
        }

        $handle = fopen($this->dataPath, 'rb');
        $head = $handle === false ? '' : (string) fread($handle, 5);
        if ($handle !== false) {
            fclose($handle);
        }
        if ($head !== '<?php') {
            return $empty;
        }

        try {
            $data = include $this->dataPath;
        } catch (Throwable) {
            return $empty;
        }

        if (! is_array($data)
            || ! is_string($data['v4'] ?? null) || strlen($data['v4']) % 5 !== 0
            || ! is_string($data['v6'] ?? null) || strlen($data['v6']) % 17 !== 0) {
            return $empty;
        }

        return ['v4' => $data['v4'], 'v6' => $data['v6']];
    }
}
