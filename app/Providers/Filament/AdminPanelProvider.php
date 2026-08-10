<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetPanelLocale;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('East Asia Point')
            // Full semantic palette, not just primary: everything in theme.css reads
            // --primary-*/--success-*/--danger-*/--warning-*/--info-*/--gray-* from
            // here, so this is the single place that changes the app's colors.
            ->colors([
                'primary' => Color::Teal,
                'success' => Color::Green,
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'info' => Color::Blue,
                'gray' => Color::Slate,
            ])
            // Inter is self-hosted via @fontsource (imported in theme.css), so the
            // panel must not also pull it from Bunny's CDN (the default provider) —
            // that would mean an external request plus a second, un-themed copy of
            // the font loading in parallel.
            ->font('Inter', provider: LocalFontProvider::class)
            // Language switcher in the user menu — same slot Filament's own
            // built-in light/dark/system theme switcher already lives in.
            // Active language shown in primary color, matching how the
            // sidebar marks the active nav item, so it doesn't need its own
            // new "selected" convention.
            ->userMenuItems([
                MenuItem::make()
                    ->label('English')
                    ->icon('heroicon-o-language')
                    ->color(fn () => (auth()->user()?->locale ?? config('app.locale')) === 'en' ? 'primary' : 'gray')
                    ->url(fn () => route('admin.locale', 'en')),
                MenuItem::make()
                    ->label('Русский')
                    ->icon('heroicon-o-language')
                    ->color(fn () => (auth()->user()?->locale ?? config('app.locale')) === 'ru' ? 'primary' : 'gray')
                    ->url(fn () => route('admin.locale', 'ru')),
            ])
            ->sidebarFullyCollapsibleOnDesktop()
            ->navigationGroups([
                // NavigationGroup has no ->translateLabel() (unlike fields/
                // columns), so these are wrapped directly. Filament matches
                // each resource to its group by comparing the resource's
                // $navigationGroup string against the registered group's
                // getLabel() (NavigationManager.php: $registeredGroup->
                // getLabel() === $groupIndex) — there's no separate "key"
                // distinct from the label. So the group identity registered
                // here must be translated the same way resources translate
                // their own getNavigationGroup() (see the override added to
                // every resource alongside getNavigationLabel()), or the
                // match silently fails and Filament falls back to an
                // unregistered, untranslated ad-hoc group per resource.
                NavigationGroup::make(__('Tours')),
                NavigationGroup::make(__('Operations')),
                NavigationGroup::make(__('Finance')),
                NavigationGroup::make(__('Manual'))
                    ->collapsed(),
                NavigationGroup::make(__('Website Management'))
                    ->collapsed(),
                NavigationGroup::make(__('Settings'))
                    ->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                SetPanelLocale::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}
