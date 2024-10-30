<?php

namespace App\Console\Commands;

use App\Services\LppApiService;
use Illuminate\Console\Command;

class PingLppApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:lpp:ping';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the connection to the LPP API';

    /**
     * Execute the console command.
     */
    public function handle(LppApiService $api): int
    {
        $this->info('Testing LPP API connection...');

        if ($api->ping()) {
            $this->info('✅ LPP API is responding');
            return Command::SUCCESS;
        }

        $this->error('❌ LPP API is not responding');
        return Command::FAILURE;
    }
}
