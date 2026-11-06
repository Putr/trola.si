<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'index'])->name('index');
Route::get('/navodila', [PageController::class, 'help'])->name('help');
Route::get('/search', [SearchController::class, 'search'])->name('search');
Route::get('/geosearch', [SearchController::class, 'geosearch'])->name('geosearch');
Route::get('/stats', [StatsController::class, 'index'])->name('stats');

// Station codes are numeric LPP codes. These catch-alls must stay last.
Route::get('/{code}/all', [StationController::class, 'showBothDirections'])->whereNumber('code')->name('station.both-directions');
Route::get('/{code}', [StationController::class, 'show'])->whereNumber('code')->name('station');
