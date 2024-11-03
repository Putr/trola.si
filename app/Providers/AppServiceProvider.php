<?php

namespace App\Providers;

use App\Services\LppApiService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Vite;

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
        Vite::prefetch(concurrency: 3);
    }
}
