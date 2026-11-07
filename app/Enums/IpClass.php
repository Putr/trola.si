<?php

namespace App\Enums;

enum IpClass: string
{
    case Counted = 'counted';
    case Datacenter = 'datacenter';
    case Foreign = 'foreign';

    /**
     * The byte stored for this class in the compiled range data.
     */
    public function byte(): int
    {
        return match ($this) {
            self::Counted => 0,
            self::Datacenter => 1,
            self::Foreign => 2,
        };
    }

    public static function fromByte(int $byte): self
    {
        return match ($byte) {
            1 => self::Datacenter,
            2 => self::Foreign,
            default => self::Counted,
        };
    }
}
