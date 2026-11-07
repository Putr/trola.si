<?php

namespace App\Services\Stats;

/**
 * Recognises requests whose User-Agent announces them as non-human.
 */
class BotDetector
{
    private const APP_MARKER = 'TrolaAndroidApp';

    public function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return true;
        }

        // CUBOT is a phone brand, not a bot, but contains the "bot" pattern.
        $haystack = str_replace('cubot', '', strtolower($userAgent));

        foreach ((array) config('stats.bot_patterns') as $pattern) {
            if (str_contains($haystack, strtolower((string) $pattern))) {
                return true;
            }
        }

        return false;
    }

    public function isApp(?string $userAgent): bool
    {
        return $userAgent !== null && str_contains($userAgent, self::APP_MARKER);
    }
}
