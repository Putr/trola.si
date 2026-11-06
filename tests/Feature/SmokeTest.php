<?php

use Illuminate\Support\Facades\Schema;

it('boots and migrates', function () {
    expect(Schema::hasTable('stations'))->toBeTrue();
});
