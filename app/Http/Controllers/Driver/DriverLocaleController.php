<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetDriverLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverLocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, SetDriverLocale::LOCALES, true), 404);

        // Session: works on the login screen, before there is a driver. Column: follows the driver
        // to a new phone.
        $request->session()->put('driver_locale', $locale);
        Auth::guard('driver')->user()?->updateQuietly(['locale' => $locale]);

        return redirect()->back(fallback: route('driver.transfers'));
    }
}
