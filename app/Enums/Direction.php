<?php

namespace App\Enums;

/**
 * Which way a visitor wants to travel relative to the city centre.
 */
enum Direction: string
{
    case ToCenter = 'to';
    case FromCenter = 'from';
    case All = 'all';

    /**
     * Unknown or missing values fall back to showing both directions.
     */
    public static function fromInput(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::All) : self::All;
    }

    /**
     * The `is_direction_to_center` value to filter on, or null for both directions.
     */
    public function toCenter(): ?bool
    {
        return match ($this) {
            self::ToCenter => true,
            self::FromCenter => false,
            self::All => null,
        };
    }
}
