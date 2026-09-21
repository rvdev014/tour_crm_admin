<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Driver\DriverExpenseController;
use App\Models\TransferDriverExpense;
use App\Models\User;
use Filament\Facades\Filament;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Receipt photos for CRM staff. The files are on a private disk, so this route is the only way to open
 * them from /admin.
 *
 * Deliberately NOT behind the stock `auth` middleware: it redirects guests to route('login'), which in
 * this project is the POST-only API login endpoint. A guest simply gets a 403 here.
 */
class AdminDriverExpenseReceiptController extends Controller
{
    public function __invoke(TransferDriverExpense $expense): StreamedResponse
    {
        // The `web` guard explicitly, never "whatever the default guard is": a driver-cabinet session is a
        // different guard and must simply be refused here, not crash on a Driver that has no staff methods.
        $user = auth('web')->user();

        abort_unless($user instanceof User && $user->canAccessPanel(Filament::getPanel('admin')), 403);

        // The same visibility rule as the transfer list (TransferResource::getEloquentQuery): admins see
        // every transfer, everyone else only the ones they created. A receipt is no more public than the
        // transfer it belongs to.
        abort_unless($user->isAdmin() || $expense->transfer?->created_by === $user->id, 403);

        return DriverExpenseController::streamReceipt($expense);
    }
}
