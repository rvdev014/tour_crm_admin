<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the admin panel's UI language from the logged-in user's saved
 * preference (users.locale), falling back to the app default (config('app.
 * locale'), currently 'ru') for guests and users who haven't picked one yet.
 *
 * Distinct from App\Http\Middleware\Locale, which is API-only and reads a
 * per-request `Lang` header for the public-facing mobile/web client — that
 * one is unrelated to this panel and stays untouched.
 */
class SetPanelLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($request->user()?->locale ?? config('app.locale'));

        return $next($request);
    }
}
