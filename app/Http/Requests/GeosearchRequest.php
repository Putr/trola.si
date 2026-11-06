<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GeosearchRequest extends FormRequest
{
    /**
     * Coordinates must be roughly inside Slovenia; LPP only covers Ljubljana.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:45,47'],
            'lon' => ['required', 'numeric', 'between:13,17'],
        ];
    }

    /**
     * Bad coordinates come from the browser's geolocation, not from the visitor,
     * so send them to manual search with the "location failed" hint instead of an error page.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(redirect()->route('search', ['locationFailed' => 1]));
    }

    public function latitude(): float
    {
        return (float) $this->validated('lat');
    }

    public function longitude(): float
    {
        return (float) $this->validated('lon');
    }
}
