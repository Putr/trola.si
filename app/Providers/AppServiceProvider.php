<?php

namespace App\Providers;

use App\Services\LppApiService;
use App\Services\Stats\IpClassifier;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LppApiService::class, fn (): LppApiService => new LppApiService(
            baseUrl: (string) config('lpp.api_url'),
            apiKey: (string) config('lpp.api_key'),
        ));

        $this->app->singleton(IpClassifier::class, fn (): IpClassifier => new IpClassifier(
            (string) config('stats.ip_data_path'),
        ));
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
