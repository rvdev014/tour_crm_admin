<?php

namespace App\Providers;

use App\Livewire\Hooks\TranslatesDatabaseErrors;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
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

        // Converts raw DB errors (duplicate name, FK-restricted delete, ...) from
        // any Filament form save or table action into an inline field error or a
        // friendly notification, instead of a raw Symfony/Ignition 500 page.
        Livewire::componentHook(TranslatesDatabaseErrors::class);

        // Login page: tagline below the form. Styled via .ep-auth-tagline in
        // theme.css instead of an inline style string, so it follows the same
        // tokens as everything else instead of hand-picked colors/spacing.
        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn () => view('filament.hooks.login-tagline'),
        );

        // App-wide footer (renders on both the auth layout and every panel page —
        // PanelsRenderHook::FOOTER is shared by simple.blade.php and index.blade.php).
        // Styled via .ep-footer in theme.css.
        FilamentView::registerRenderHook(
            PanelsRenderHook::FOOTER,
            fn () => view('filament.hooks.footer'),
        );

        // Every form field and table column supports ->translateLabel(), which
        // wraps getLabel() in __() — but it defaults off per-instance, and most
        // of this app's fields never call it. Rather than adding
        // ->translateLabel() to hundreds of individual field/column
        // definitions, turn it on globally here: this also covers *implicit*
        // labels (no explicit ->label() at all, e.g. TextInput::make
        // ('arrival_time') auto-humanizing to "Arrival time"), which is most
        // of them — those aren't touched by the explicit ->label(__('...'))
        // wrapping already applied elsewhere, since there's no string literal
        // to wrap. Translation only actually applies where lang/ru.json has a
        // matching key; anything not yet in that dictionary just falls back to
        // the (English) auto-humanized text, same as before this was added.
        $this->app->resolving(function ($object) {
            if ($object instanceof \Filament\Forms\Components\Component
                || $object instanceof \Filament\Tables\Columns\Column) {
                $object->translateLabel();
            }
        });
    }
}
