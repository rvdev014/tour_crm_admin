<?php

namespace App\Providers;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use PhpOffice\PhpWord\Settings as PhpWordSettings;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Without this, PHPWord writes text into .docx XML unescaped (Settings
        // defaults to false), so any value containing &, <, or > silently
        // corrupts the document and Word refuses to open it. Applies to every
        // PHPWord-based export (ExportTransferService, ExportTablichkaService,
        // ExportHotelService's TemplateProcessor::setValue).
        PhpWordSettings::setOutputEscapingEnabled(true);

        if (env('APP_SCHEME') === 'https') {
            URL::forceScheme('https');
        }

        FilamentAsset::register([
            Css::make('custom-stylesheet', __DIR__.'/../../resources/custom-css/admin.css'),
        ]);

        // Amber glow orb injected into login page background
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_START,
            fn (): HtmlString => new HtmlString(
                '<div aria-hidden="true" style="position:fixed;width:480px;height:480px;border-radius:50%;'
                .'background:radial-gradient(circle,rgba(245,158,11,0.07) 0%,transparent 68%);'
                .'top:35%;right:-100px;animation:blob-drift-2 18s ease-in-out infinite;'
                .'pointer-events:none;z-index:0;"></div>'
            ),
        );

        // Login page: tagline below the form
        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): HtmlString => new HtmlString(
                '<p style="text-align:center;margin-top:1.25rem;color:rgba(100,130,170,0.55);'
                .'font-size:0.6875rem;letter-spacing:0.06em;font-family:Inter,sans-serif;'
                .'text-transform:uppercase;">East Asia Point · Tour Management</p>'
            ),
        );

        // Footer on login page
        FilamentView::registerRenderHook(
            PanelsRenderHook::FOOTER,
            fn (): HtmlString => new HtmlString(
                '<div style="text-align:center;padding:0.75rem 0 2rem;color:rgba(100,120,150,0.35);'
                .'font-size:0.6875rem;letter-spacing:0.05em;font-family:Inter,sans-serif;'
                .'position:relative;z-index:2;">© '.date('Y').' East Asia Point</div>'
            ),
        );
    }
}
