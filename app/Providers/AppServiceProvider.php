<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = config('app.url');

        if (! empty($appUrl)) {
            URL::forceRootUrl($appUrl);
            URL::forceScheme(parse_url($appUrl, PHP_URL_SCHEME) ?: 'https');
        }
    }
}
