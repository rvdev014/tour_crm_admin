<?php

use App\Http\Controllers\Driver\DriverAuthController;
use App\Http\Controllers\Driver\DriverLocaleController;
use App\Http\Controllers\Driver\DriverTransferController;
use App\Models\Driver;
use App\Services\DriverTransferQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Driver cabinet routes
|--------------------------------------------------------------------------
|
| Mounted at /driver with the `web` + `driver.locale` middleware by RouteServiceProvider.
| Authenticated with the separate `driver` guard (see config/auth.php).
|
*/

// Ownership is enforced here, once, instead of in every controller: a transfer that is not this
// driver's resolves to 404 (not 403 — no confirming that an id exists), and a route added later
// cannot forget the check.
//
// The parameter is deliberately NOT called {transfer}: `export-transfer/{transfer}` in routes/web.php
// relies on ordinary implicit model binding, and Route::bind() is global by parameter name.
Route::bind('driverTransfer', function (string $id) {
    $driver = Auth::guard('driver')->user();

    abort_unless($driver instanceof Driver, 404);

    return DriverTransferQuery::forDriver($driver)->findOrFail($id);
});

Route::middleware('driver.nocache')->group(function () {
    Route::middleware('guest.driver')->group(function () {
        Route::get('login', [DriverAuthController::class, 'showLogin'])->name('login');
        Route::post('login', [DriverAuthController::class, 'login'])
            ->middleware('throttle:driver-login')
            ->name('login.attempt');
    });

    // Public on purpose: the language switch has to work on the login screen.
    Route::get('locale/{locale}', DriverLocaleController::class)->name('locale');

    Route::middleware('auth.driver:driver')->group(function () {
        Route::get('/', fn () => redirect()->route('driver.transfers'))->name('home');
        Route::post('logout', [DriverAuthController::class, 'logout'])->name('logout');

        Route::get('transfers', [DriverTransferController::class, 'index'])->name('transfers');

        Route::prefix('transfers/{driverTransfer}')->whereNumber('driverTransfer')->group(function () {
            Route::get('/', [DriverTransferController::class, 'show'])->name('transfers.show');
            Route::get('complete', [DriverTransferController::class, 'complete'])->name('transfers.complete');
            Route::post('status', [DriverTransferController::class, 'updateStatus'])->name('transfers.status');
        });
    });
});
