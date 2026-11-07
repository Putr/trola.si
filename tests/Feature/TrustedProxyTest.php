<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_ip', fn () => request()->ip());
});

it('takes the visitor address from a trusted Docker proxy hop', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
        ->withHeader('X-Forwarded-For', '8.8.8.8, 93.103.1.1')
        ->get('/_ip')
        ->assertContent('93.103.1.1');
});

it('ignores X-Forwarded-For from an untrusted client', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '93.103.1.1'])
        ->withHeader('X-Forwarded-For', '8.8.8.8')
        ->get('/_ip')
        ->assertContent('93.103.1.1');
});
