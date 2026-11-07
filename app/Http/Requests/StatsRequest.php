<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string of /stats. The page is public and link-driven, so nothing here may fail:
 * anything unexpected (junk, arrays, huge values) falls back to the defaults.
 */
class StatsRequest extends FormRequest
{
    public const LISTS = ['top', 'rising', 'falling', 'least'];

    public const MONTHS = [3, 6, 12];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function months(): int
    {
        $months = $this->query('obdobje');

        return is_string($months) && in_array((int) $months, self::MONTHS, true) && (string) (int) $months === $months
            ? (int) $months
            : self::MONTHS[0];
    }

    public function list(): string
    {
        $list = $this->query('seznam');

        return is_string($list) && in_array($list, self::LISTS, true) ? $list : self::LISTS[0];
    }

    public function stationTerm(): ?string
    {
        $term = $this->query('postaja');
        if (! is_string($term)) {
            return null;
        }

        $term = trim(mb_substr(trim($term), 0, 100));

        return $term === '' ? null : $term;
    }
}
