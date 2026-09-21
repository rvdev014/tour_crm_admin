<?php

namespace App\Http\Controllers\Driver;

use App\Enums\DriverExpenseStatus;
use App\Enums\DriverTransferStatus;
use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverExpense;
use App\Models\TransferDriverStatusLog;
use App\Services\DriverExpenseQuery;
use App\Services\DriverTransferQuery;
use App\Services\DriverTransferStatusService;
use App\Support\DriverExpensePresenter;
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
        $viewer = $this->viewer();

        $today = Carbon::now(self::TIMEZONE)->startOfDay();
        $selected = $this->parseDate($request->query('date')) ?? $today;

        // Viewing today: yesterday..+5. Viewing another day: a week centred on it.
        [$from, $to] = $selected->isSameDay($today)
            ? [$today->copy()->subDay(), $today->copy()->addDays(5)]
            : [$selected->copy()->subDays(3), $selected->copy()->addDays(3)];

        $counts = DriverTransferQuery::countPerDay($viewer, $from, $to);

        $strip = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $strip[] = [
                'date' => $day->copy(),
                'count' => $counts[$day->toDateString()] ?? 0,
                'active' => $day->isSameDay($selected),
                'today' => $day->isSameDay($today),
            ];
        }

        $models = DriverTransferQuery::forDriver($viewer)
            ->whereDate('date_time', $selected->toDateString())
            ->with(['toCity:id,name', 'fromCity:id,name'])
            ->orderBy('date_time')
            ->get();

        // Driver contact info goes to dispatchers only — see DriverTransferQuery::driversFor().
        $drivers = $viewer->isDispatcher() ? DriverTransferQuery::driversFor($models) : null;

        return view('driver.transfers.index', [
            'driver' => $viewer,
            'isDispatcher' => $viewer->isDispatcher(),
            'selected' => $selected,
            'today' => $today,
            'strip' => $strip,
            'transfers' => $models->map(fn (Transfer $t) => new DriverTransferPresenter($t, $drivers)),
        ]);
    }

    public function show(Transfer $driverTransfer): View
    {
        $viewer = $this->viewer();

        // Only the viewer's own entries for a driver; every entry for a dispatcher (see DriverExpenseQuery).
        $expenses = DriverExpenseQuery::forTransfer($viewer, $driverTransfer)->get();

        // A rejected expense is not money the trip cost, so it is left out of the total.
        $total = $expenses->where('status', '!=', DriverExpenseStatus::Rejected)->sum('amount');

        return view('driver.transfers.show', [
            't' => $this->present($driverTransfer),
            'isDispatcher' => $viewer->isDispatcher(),
            'statuses' => DriverTransferStatus::cases(),
            'expenses' => $expenses->map(fn (TransferDriverExpense $e) => new DriverExpensePresenter($e, $viewer)),
            'expensesTotal' => $total > 0 ? number_format((float) $total, 0, '.', ' ').' '.TransferDriverExpense::CURRENCY : null,
        ]);
    }

    /** Server-rendered confirmation for the last step, so a mis-tap is recoverable without any JavaScript. */
    public function complete(Transfer $driverTransfer): View|RedirectResponse
    {
        $t = $this->present($driverTransfer);

        // Dispatchers pick a status from a list instead; there is no "last step" for them.
        if ($this->viewer()->isDispatcher() || $t->next() !== DriverTransferStatus::Completed) {
            return redirect()->route('driver.transfers.show', $driverTransfer);
        }

        return view('driver.transfers.complete', ['t' => $t]);
    }

    public function updateStatus(Request $request, Transfer $driverTransfer): RedirectResponse
    {
        $viewer = $this->viewer();
        $back = redirect()->route('driver.transfers.show', $driverTransfer);
        $t = new DriverTransferPresenter($driverTransfer);

        // Closed by the operator in the CRM: nobody in the cabinet reopens it (an admin can).
        if ($t->isClosed) {
            return $back->with('driver_error', __('driver.flow.closed'));
        }

        return $viewer->isDispatcher()
            ? $this->updateAsDispatcher($request, $driverTransfer, $viewer, $back)
            : $this->updateAsDriver($request, $driverTransfer, $viewer, $back, $t);
    }

    /** A dispatcher may set ANY status, in any direction; every change is logged under their name. */
    private function updateAsDispatcher(Request $request, Transfer $transfer, Driver $dispatcher, RedirectResponse $back): RedirectResponse
    {
        $request->validate(['to' => ['required', Rule::enum(DriverTransferStatus::class)]]);

        $to = DriverTransferStatus::from($request->input('to'));

        $changed = DriverTransferStatusService::apply($transfer, $to, $dispatcher, TransferDriverStatusLog::SOURCE_DISPATCHER);

        // Picking the status it already has is a no-op, not an error.
        return $changed
            ? $back->with('driver_notice', __('driver.flow.updated', ['status' => $to->getLabel()]))
            : $back;
    }

    /** A driver moves forward exactly one step, from the state their page was showing. */
    private function updateAsDriver(Request $request, Transfer $transfer, Driver $driver, RedirectResponse $back, DriverTransferPresenter $t): RedirectResponse
    {
        $request->validate([
            'from' => ['required', Rule::enum(DriverTransferStatus::class)],
            'to' => ['required', Rule::enum(DriverTransferStatus::class)],
        ]);

        $from = DriverTransferStatus::from($request->input('from'));
        $to = DriverTransferStatus::from($request->input('to'));

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
            DriverTransferStatusService::apply($transfer, $to, $driver);
        } catch (DomainException) {
            return $back->with('driver_error', __('driver.flow.stale'));
        }

        return $back->with('driver_notice', __('driver.flow.updated', ['status' => $to->getLabel()]));
    }

    private function viewer(): Driver
    {
        /** @var Driver $viewer */
        $viewer = Auth::guard('driver')->user();

        return $viewer;
    }

    private function present(Transfer $transfer): DriverTransferPresenter
    {
        $transfer->loadMissing(['toCity:id,name', 'fromCity:id,name']);

        $viewer = $this->viewer();
        $drivers = $viewer->isDispatcher() ? DriverTransferQuery::driversFor([$transfer]) : null;

        return new DriverTransferPresenter($transfer, $drivers, $this->mayContactClient($viewer, $transfer));
    }

    /**
     * Who gets the client's phone number. A dispatcher always. A driver only while the trip is still open:
     * once the operator has closed it (Done) the driver has no further need of the number, and it should not
     * stay reachable in their history. The presenter enforces it, so a closed trip's HTML contains no number.
     */
    private function mayContactClient(Driver $viewer, Transfer $transfer): bool
    {
        return $viewer->isDispatcher() || $transfer->status !== ExpenseStatus::Done;
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
