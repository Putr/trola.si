<?php

use App\Support\FulltextQuery;

it('turns words into prefix terms', function () {
    expect(FulltextQuery::booleanPrefix('Bavarski  dvor'))->toBe('Bavarski* dvor*');
});

it('keeps Slovenian letters', function () {
    expect(FulltextQuery::booleanPrefix('Šiška'))->toBe('Šiška*');
});

it('drops operators and returns null when nothing is left', function (string $input) {
    expect(FulltextQuery::booleanPrefix($input))->toBeNull();
})->with(['*', '+-', '"@"', '%_', '   ']);

it('escapes LIKE wildcards', function () {
    expect(FulltextQuery::escapeLike('50%_a\\b'))->toBe('50\\%\\_a\\\\b');
});
