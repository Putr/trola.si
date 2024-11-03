<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\StationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [Controller::class, 'index'])->name('index');
Route::get('/search', [Controller::class, 'search'])->name('search');
Route::get('/{code}/all', [Controller::class, 'showBothDirections'])->name('station.both-directions');
Route::get('/{code}', [Controller::class, 'show'])->name('station');
