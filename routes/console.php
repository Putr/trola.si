<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Schedule tasks
Schedule::command('db:seed')->dailyAt('03:00');
Schedule::command('sitemap:generate')->dailyAt('03:30');

// Run station cache warming at 3 AM every day
Schedule::command('stations:warm-cache')
    ->dailyAt('03:20')
    ->withoutOverlapping()
    ->runInBackground();

// Run full stats aggregation at 2 AM every day (for completed days)
Schedule::command('stats:aggregate')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Update today's stats every hour to keep trend data current
Schedule::command('stats:aggregate --today-only')
    ->everyHour()
    ->withoutOverlapping()
    ->runInBackground();
