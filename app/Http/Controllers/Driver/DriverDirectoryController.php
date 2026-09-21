<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\DriverTransferQuery;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Drivers" page for dispatchers: everyone who can be assigned to a trip, how to reach them and how
 * many trips they have today. Behind the `driver.dispatcher` middleware (404 for a plain driver).
 */
class DriverDirectoryController extends Controller
{
    public function __invoke(): View
    {
        /** @var Driver $viewer */
        $viewer = Auth::guard('driver')->user();

        $today = Carbon::now('Asia/Tashkent')->startOfDay();
        $trips = DriverTransferQuery::tripsPerDriverOn($viewer, $today);

        // Contact columns only. Never chat_id, the password hash or phone_normalized. Dispatchers are
        // staff, not vehicles, so they are not listed.
        $drivers = Driver::query()
            ->assignable()
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'car_number', 'car_model', 'is_active'])
            ->map(fn (Driver $driver) => [
                'name' => $driver->name,
                'phone' => filled($driver->phone) ? $driver->phone : null,
                'tel' => PhoneNormalizer::tel($driver->phone),
                'car' => implode(' · ', array_filter([trim((string) $driver->car_model), trim((string) $driver->car_number)])) ?: null,
                'active' => (bool) $driver->is_active,
                'trips' => $trips[$driver->id] ?? 0,
            ]);

        return view('driver.drivers.index', [
            'today' => $today,
            'drivers' => $drivers,
            'isDispatcher' => true,
        ]);
    }
}
