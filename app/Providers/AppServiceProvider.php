<?php

namespace App\Providers;

use App\Services\LppApiService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LppApiService::class, function ($app) {
            return new LppApiService();
        });
    }

    public function boot(): void
    {
        // Force HTTPS for all URL generation when not in local environment
        if ($this->app->environment('production', 'staging')) {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);
    }
}
