<?php

namespace App\Providers;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Model;
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
        if (env('APP_SCHEME') === 'https') {
            URL::forceScheme('https');
        }

//        Model::preventLazyLoading(! $this->app->isProduction());

        FilamentAsset::register([
            Css::make('custom-stylesheet', __DIR__ . '/../../resources/custom-css/admin.css'),
        ]);
    }
}
