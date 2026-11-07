<?php

namespace App\Support;

use Illuminate\Support\Str;

class StationName
{
    /**
     * Compare names the way MySQL's utf8mb4_unicode_ci did when pairing ran in SQL:
     * ignoring case, accents and surrounding spaces.
     */
    public static function key(string $name): string
    {
        return Str::lower(Str::ascii(trim($name)));
    }
}
