<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Five sign-in attempts per minute for each email and IP address.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // In local development the white parts of the logo are red (see routes/web.php).
        View::composer('*', function ($view) {
            $view->with('logoUrl', $this->app->environment('local')
                ? '/logo-dev.svg'
                : '/logo.svg?v='.filemtime(public_path('logo.svg')));
        });
    }
}
