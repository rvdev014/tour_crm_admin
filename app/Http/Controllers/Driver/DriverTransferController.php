<?php

namespace App\Http\Controllers\Driver;

use App\Enums\DriverTransferStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Transfer;
use App\Services\DriverTransferQuery;
use App\Services\DriverTransferStatusService;
use App\Support\DriverTransferPresenter;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class DriverTransferController extends Controller
{
    /** Transfer times are stored as naive local times; the CRM (TourService::notifyDrivers) reads them as Tashkent. */
    private const TIMEZONE = 'Asia/Tashkent';

    public function index(Request $request): View
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $today = Carbon::now(self::TIMEZONE)->startOfDay();
        $selected = $this->parseDate($request->query('date')) ?? $today;

        // Viewing today: yesterday..+5. Viewing another day: a week centred on it.
        [$from, $to] = $selected->isSameDay($today)
            ? [$today->copy()->subDay(), $today->copy()->addDays(5)]
            : [$selected->copy()->subDays(3), $selected->copy()->addDays(3)];

        $counts = DriverTransferQuery::countPerDay($driver, $from, $to);

        $strip = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $strip[] = [
                'date' => $day->copy(),
                'count' => $counts[$day->toDateString()] ?? 0,
                'active' => $day->isSameDay($selected),
                'today' => $day->isSameDay($today),
            ];
        }

        $transfers = DriverTransferQuery::forDriver($driver)
            ->whereDate('date_time', $selected->toDateString())
            ->with(['toCity:id,name', 'fromCity:id,name'])
            ->orderBy('date_time')
            ->get()
            ->map(fn (Transfer $t) => new DriverTransferPresenter($t));

        return view('driver.transfers.index', [
            'driver' => $driver,
            'selected' => $selected,
            'today' => $today,
            'strip' => $strip,
            'transfers' => $transfers,
        ]);
    }

    public function show(Transfer $driverTransfer): View
    {
        return view('driver.transfers.show', ['t' => $this->present($driverTransfer)]);
    }

    /** Server-rendered confirmation for the last step, so a mis-tap is recoverable without any JavaScript. */
    public function complete(Transfer $driverTransfer): View|RedirectResponse
    {
        $t = $this->present($driverTransfer);

        if ($t->next() !== DriverTransferStatus::Completed) {
            return redirect()->route('driver.transfers.show', $driverTransfer);
        }

        return view('driver.transfers.complete', ['t' => $t]);
    }

    public function updateStatus(Request $request, Transfer $driverTransfer): RedirectResponse
    {
        $request->validate([
            'from' => ['required', Rule::enum(DriverTransferStatus::class)],
            'to' => ['required', Rule::enum(DriverTransferStatus::class)],
        ]);

        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();
        $from = DriverTransferStatus::from($request->input('from'));
        $to = DriverTransferStatus::from($request->input('to'));
        $back = redirect()->route('driver.transfers.show', $driverTransfer);
        $t = new DriverTransferPresenter($driverTransfer);

        if ($t->isClosed) {
            return $back->with('driver_error', __('driver.flow.closed'));
        }

        // Already there — e.g. a double tap on a slow connection. That is success, not an error.
        if ($t->status === $to) {
            return $back;
        }

        // The page the driver tapped on was showing a different state than the server now has.
        // Refuse rather than silently skipping a step.
        if ($t->status !== $from) {
            return $back->with('driver_error', __('driver.flow.stale'));
        }

        try {
            DriverTransferStatusService::apply($driverTransfer, $to, $driver);
        } catch (DomainException) {
            return $back->with('driver_error', __('driver.flow.stale'));
        }

        return $back->with('driver_notice', __('driver.flow.updated', ['status' => $to->getLabel()]));
    }

    private function present(Transfer $transfer): DriverTransferPresenter
    {
        return new DriverTransferPresenter($transfer->loadMissing(['toCity:id,name', 'fromCity:id,name']));
    }

    /** A malformed ?date= falls back to today instead of erroring. */
    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, self::TIMEZONE);
        } catch (Throwable) {
            return null;
        }

        // createFromFormat happily rolls 2026-02-31 over to March; only accept a faithful round-trip.
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
