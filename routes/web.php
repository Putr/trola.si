<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\StationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [Controller::class, 'index']);
Route::get('/{code}/all', [Controller::class, 'showBothDirections']);
Route::get('/{code}', [Controller::class, 'show']);
