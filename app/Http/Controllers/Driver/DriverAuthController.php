<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Support\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DriverAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('driver.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:255'],
        ], [], [
            'phone' => mb_strtolower(__('driver.auth.phone')),
            'password' => mb_strtolower(__('driver.auth.password')),
        ]);

        $phone = PhoneNormalizer::uz($data['phone']);

        // remember = true: a driver should not have to re-enter a password on the same phone every
        // few hours. An unusable phone number and a wrong password produce the same message, so the
        // form cannot be used to probe which numbers exist.
        $authenticated = $phone !== null && Auth::guard('driver')->attempt([
            'phone_normalized' => $phone,
            'is_active' => true,
            'password' => $data['password'],
        ], true);

        if (! $authenticated) {
            throw ValidationException::withMessages(['phone' => __('driver.auth.failed')]);
        }

        $request->session()->regenerate();
        Auth::guard('driver')->user()->updateQuietly(['last_login_at' => now()]);

        return redirect()->route('driver.transfers');
    }

    public function logout(Request $request): RedirectResponse
    {
        // Deliberately NOT session()->invalidate(): that would flush the whole session and also log a
        // CRM operator out of /admin if they share this browser. Guard::logout() only clears the
        // driver guard's own keys.
        Auth::guard('driver')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('driver.login');
    }
}
