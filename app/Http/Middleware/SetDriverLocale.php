<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the driver cabinet's language: the choice made in this browser (session) wins, then the
 * driver's saved preference (drivers.locale, so it follows them to a new phone), then the app default.
 *
 * Only ru and uz are offered. Deliberately NOT App\Enums\Lang, which is for the _en/_ru/_uz database
 * column suffixes and includes English — reusing it would silently open an untranslated cabinet.
 */
class SetDriverLocale
{
    public const LOCALES = ['ru', 'uz'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('driver_locale')
            ?? Auth::guard('driver')->user()?->locale;

        App::setLocale(in_array($locale, self::LOCALES, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
