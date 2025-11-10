<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * In Laravel 11, commands are auto-discovered from app/Console/Commands,
     * but you can still manually register commands here if needed.
     *
     * @var array
     */
    protected $commands = [
        // Commands are auto-discovered, but you can add custom registrations here if needed
    ];

    /**
     * Register the commands for the application.
     *
     * In Laravel 11, scheduling is done in routes/console.php using the Schedule facade.
     */
    protected function commands(): void
    {
        // Commands are auto-discovered from app/Console/Commands
        // You can also load commands from a directory:
        // $this->load(__DIR__.'/Commands');
    }
}
