<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
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
        // In local development the white parts of the logo are red (see routes/web.php).
        View::composer('*', function ($view) {
            $view->with('logoUrl', $this->app->environment('local')
                ? '/logo-dev.svg'
                : '/logo.svg?v='.filemtime(public_path('logo.svg')));
        });
    }
}
