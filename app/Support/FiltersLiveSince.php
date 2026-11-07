<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

/**
 * The configured day the traffic filters went live, parsed defensively: a malformed
 * STATS_FILTERS_SINCE falls back to the default instead of breaking /stats.
 */
class FiltersLiveSince
{
    private const FALLBACK = '2026-11-07';

    public static function date(): Carbon
    {
        try {
            return Carbon::createFromFormat('!Y-m-d', (string) config('stats.filters_live_since')) ?: Carbon::parse(self::FALLBACK)->startOfDay();
        } catch (Throwable) {
            return Carbon::parse(self::FALLBACK)->startOfDay();
        }
    }
}
