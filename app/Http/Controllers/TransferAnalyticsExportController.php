<?php

namespace App\Http\Controllers;

use App\Services\Analytics\TransferAnalytics;
use App\Services\Analytics\TransferAnalyticsWorkbook;
use App\Support\AnalyticsAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads the owner's Excel report. Read-only, so a GET; opened from the analytics page in the same browser
 * session.
 *
 * Not behind the stock `auth` middleware (it would redirect a guest to route('login'), the POST-only API endpoint):
 * a guest, or anyone who is not Admin/Accountant, just gets a 403.
 */
class TransferAnalyticsExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $user = auth('web')->user();

        abort_unless(AnalyticsAccess::allows($user), 403);

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'until' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sources' => ['nullable', 'array'],
            'sources.*' => [Rule::in(TransferAnalytics::SOURCES)],
        ]);

        $from = Carbon::createFromFormat('!Y-m-d', $data['from']);
        $until = Carbon::createFromFormat('!Y-m-d', $data['until']);

        abort_if($from->diffInDays($until) > TransferAnalytics::MAX_DAYS, 422, 'The period is too long.');

        // This route is outside the panel's middleware stack, so pick the user's language here (ru/en only).
        App::setLocale(in_array($user->locale, ['ru', 'en'], true) ? $user->locale : 'ru');

        $report = TransferAnalytics::make()->build($from, $until, $data['sources'] ?? null);
        $workbook = TransferAnalyticsWorkbook::build($report, $user->name);

        $path = tempnam(sys_get_temp_dir(), 'transfer-report');
        TransferAnalyticsWorkbook::write($workbook, $path);
        $workbook->disconnectWorksheets();

        $name = __('analytics.file').'_'.$data['from'].'_'.$data['until'].'.xlsx';

        return response()
            ->download($path, $name, ['Cache-Control' => 'private, no-store'])
            ->deleteFileAfterSend(true);
    }
}
