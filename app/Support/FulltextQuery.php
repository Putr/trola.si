<?php

namespace App\Support;

/**
 * Makes untrusted search input safe for MySQL boolean-mode FULLTEXT and LIKE.
 */
class FulltextQuery
{
    /**
     * Keep only letters and digits (dropping boolean-mode operators and anything else
     * InnoDB's parser chokes on) and turn every word into a prefix match
     * ("bavar dvo" becomes "bavar* dvo*"). Returns null when nothing searchable is left.
     */
    public static function booleanPrefix(string $input): ?string
    {
        $clean = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $input));

        if ($clean === '') {
            return null;
        }

        $terms = preg_split('/\s+/u', $clean) ?: [];

        return implode(' ', array_map(fn (string $term): string => $term.'*', $terms));
    }

    /**
     * Escape LIKE wildcards so they match literally.
     */
    public static function escapeLike(string $input): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $input);
    }
}
