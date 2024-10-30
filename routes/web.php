<?php

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;

Route::get('/', [Controller::class, 'index']);
Route::get('/{stopId}', [Controller::class, 'show']);
