<?php

namespace App\Http\Middleware;

use App\Enums\Lang;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class Locale
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerLocale = $request->header('Lang');
        App::setLocale($headerLocale ?: Lang::default()->value);
        return $next($request);
    }
}
