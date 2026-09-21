<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dispatcher-only pages. A plain driver gets a 404, not a 403: there is nothing to confirm about a page
 * they were never meant to know exists. Runs after `auth.driver:driver`.
 */
class EnsureDriverIsDispatcher
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::guard('driver')->user()?->isDispatcher(), 404);

        return $next($request);
    }
}
