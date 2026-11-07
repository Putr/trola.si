<?php

use Illuminate\Support\Facades\Schedule;

// Schedule tasks
Schedule::command('stations:sync')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('sitemap:generate')->dailyAt('03:30');

// Refresh cached station lookups after the nightly sync
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
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Refresh the iptoasn data used to filter datacenter and non-European traffic from the stats
Schedule::command('stats:update-ip-data')
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping();
