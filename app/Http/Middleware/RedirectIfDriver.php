<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an already-logged-in driver off the login page. Replaces the stock `guest` alias, whose
 * target (RouteServiceProvider::HOME = '/home') does not exist in this app.
 */
class RedirectIfDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('driver')->check()) {
            return redirect()->route('driver.transfers');
        }

        return $next($request);
    }
}
