<?php

namespace App\Providers;

use App\Support\PhoneNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function(Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Two limits: a tight one per phone+IP (stops guessing one driver's password) and a looser
        // one per IP (stops spraying many phone numbers from one address).
        RateLimiter::for('driver-login', function(Request $request) {
            $phone = PhoneNormalizer::uz((string) $request->input('phone')) ?? 'invalid';

            return [
                Limit::perMinute(5)->by($phone.'|'.$request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        // Each submission uploads a photo, so cap them per account: generous for a real driver entering a
        // day's expenses, tight enough that a stuck retry loop cannot fill the disk.
        RateLimiter::for('driver-expenses', function(Request $request) {
            $viewer = $request->user('driver');

            return Limit::perMinute(20)->by('expenses|'.($viewer?->getKey() ?? $request->ip()));
        });

        $this->routes(function() {
            Route::middleware(['api', 'locale'])
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware(['web', 'driver.locale'])
                ->prefix('driver')
                ->name('driver.')
                ->group(base_path('routes/driver.php'));
        });
    }
}
