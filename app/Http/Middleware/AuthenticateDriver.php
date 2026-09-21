<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Use as `auth.driver:driver`.
 *
 * The app's stock `auth` middleware redirects guests to route('login'), which in this project is the
 * POST-only API login endpoint — a guest would land on a 405. Drivers get their own login page.
 */
class AuthenticateDriver extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('driver.login');
    }
}
