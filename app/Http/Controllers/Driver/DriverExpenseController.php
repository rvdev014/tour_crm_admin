<?php

namespace App\Http\Controllers\Driver;

use App\Enums\DriverExpenseType;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverExpense;
use App\Services\DriverExpenseService;
use App\Support\DriverTransferPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trip expenses entered from the cabinet: a type, an amount in sums and a photo of the receipt.
 *
 * Transfers and expenses arrive already scoped to what this account may touch (route binders
 * `driverTransfer` / `driverExpense`), so nothing here re-checks ownership.
 */
class DriverExpenseController extends Controller
{
    /** Sanity cap, in sums — roughly 80,000 USD. Anything larger is a typo. */
    private const MAX_AMOUNT = 1_000_000_000;

    /** Photo size limit in KB (Laravel's `max` unit). */
    private const MAX_PHOTO_KB = 10240;

    public function create(Transfer $driverTransfer): View
    {
        return view('driver.expenses.create', [
            't' => new DriverTransferPresenter($driverTransfer->loadMissing(['toCity:id,name', 'fromCity:id,name'])),
            'types' => DriverExpenseType::cases(),
            // A fresh token per rendered form. A retry re-sends the same one (see DriverExpenseService::add).
            'submissionKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, Transfer $driverTransfer): RedirectResponse
    {
        /** @var Driver $viewer */
        $viewer = Auth::guard('driver')->user();

        $data = $request->validate([
            'type' => ['required', Rule::enum(DriverExpenseType::class)],
            'amount' => ['required', 'string', 'max:24', function (string $attribute, mixed $value, \Closure $fail) {
                if (self::parseAmount($value) === null) {
                    $fail(__('driver.expenses.errors.amount'));
                }
            }],
            // jpg/png/webp only, on purpose: iOS Safari converts HEIC to JPEG for an `image/jpeg,...` file
            // input, whereas a HEIC upload would not display in an operator's desktop browser. `mimes`
            // checks the file's CONTENT, so a renamed script or SVG is refused.
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_PHOTO_KB],
            'submission_key' => ['required', 'uuid'],
        ], [
            'type.required' => __('driver.expenses.errors.type'),
            'type.enum' => __('driver.expenses.errors.type'),
            'amount.required' => __('driver.expenses.errors.amount'),
            // A photo over the server's request limit arrives as NO file at all, so "missing" and "too big"
            // cannot be told apart; say both.
            'receipt.required' => __('driver.expenses.errors.receipt_missing'),
            'receipt.uploaded' => __('driver.expenses.errors.receipt_missing'),
            'receipt.mimes' => __('driver.expenses.errors.receipt_format'),
            'receipt.max' => __('driver.expenses.errors.receipt_size'),
            'receipt.file' => __('driver.expenses.errors.receipt_format'),
        ]);

        DriverExpenseService::add(
            $driverTransfer,
            $viewer,
            DriverExpenseType::from($data['type']),
            self::parseAmount($data['amount']),
            $request->file('receipt'),
            $data['submission_key'],
        );

        return redirect()
            ->route('driver.transfers.show', $driverTransfer)
            ->with('driver_notice', __('driver.expenses.added'));
    }

    /** Confirmation page before deleting, so a mis-tap is recoverable without JavaScript. */
    public function confirmDelete(TransferDriverExpense $driverExpense): View|RedirectResponse
    {
        if (! $driverExpense->canBeDeletedBy($this->viewer())) {
            return $this->cannotDelete($driverExpense);
        }

        return view('driver.expenses.delete', [
            'expense' => $driverExpense->load('addedBy:id,name,role'),
            'transferId' => $driverExpense->transfer_id,
        ]);
    }

    public function destroy(TransferDriverExpense $driverExpense): RedirectResponse
    {
        if (! $driverExpense->canBeDeletedBy($this->viewer())) {
            return $this->cannotDelete($driverExpense);
        }

        $transferId = $driverExpense->transfer_id;
        $driverExpense->delete();   // also removes the photo from disk (TransferDriverExpense::deleted)

        return redirect()
            ->route('driver.transfers.show', $transferId)
            ->with('driver_notice', __('driver.expenses.deleted'));
    }

    /** The receipt photo, through an authenticated route — it lives on a private disk, not under public/. */
    public function receipt(TransferDriverExpense $driverExpense): StreamedResponse
    {
        return self::streamReceipt($driverExpense);
    }

    public static function streamReceipt(TransferDriverExpense $expense): StreamedResponse
    {
        $disk = Storage::disk(TransferDriverExpense::DISK);

        abort_unless($disk->exists($expense->receipt_path), 404);

        return $disk->response($expense->receipt_path, null, [
            // The stored content type is the one detected from the file's bytes at upload.
            'Content-Type' => $expense->receipt_mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * A whole number of sums between 1 and MAX_AMOUNT, or null.
     *
     * Accepts plain digits ("150000") and digits grouped in thousands with a space, comma, dot or
     * apostrophe ("150 000", "150,000", "1.500.000"). Anything else is REJECTED rather than guessed at:
     * UZS has no small change, so "12.5" is a mistake, and reading it as 125 would silently record ten
     * times the money.
     */
    public static function parseAmount(mixed $raw): ?int
    {
        // Non-breaking spaces are what a phone keyboard inserts as a thousands separator.
        $text = trim(str_replace(["\u{00A0}", "\u{202F}"], ' ', (string) $raw));

        if (preg_match('/^\d{1,12}$/', $text)) {
            $digits = $text;
        } elseif (preg_match('/^\d{1,3}(?:[ .,\']\d{3})+$/', $text)) {
            $digits = preg_replace('/\D/', '', $text);
        } else {
            return null;
        }

        $amount = (int) $digits;

        return ($amount >= 1 && $amount <= self::MAX_AMOUNT) ? $amount : null;
    }

    private function cannotDelete(TransferDriverExpense $expense): RedirectResponse
    {
        return redirect()
            ->route('driver.transfers.show', $expense->transfer_id)
            ->with('driver_error', __('driver.expenses.cannot_delete'));
    }

    private function viewer(): Driver
    {
        /** @var Driver $viewer */
        $viewer = Auth::guard('driver')->user();

        return $viewer;
    }
}
