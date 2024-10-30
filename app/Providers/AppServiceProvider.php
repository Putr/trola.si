<?php

namespace App\Providers;

use App\Services\LppApiService;
use Illuminate\Support\ServiceProvider;

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
}
