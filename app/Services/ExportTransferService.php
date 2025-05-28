<?php

namespace App\Services;

use App\Enums\ExpenseType;
use App\Models\Driver;
use App\Models\Tour;
use App\Models\TourDay;
use App\Models\TourDayExpense;
use App\Models\TourRoomType;
use App\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\TemplateProcessor;

class ExportTransferService
{
    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public static function getReplacedTemplateForTransfer(Transfer $transfer): TemplateProcessor
    {
        $templateProcessor = new TemplateProcessor(__DIR__ . '/Templates/Transfer_voucher.docx');

        $driversNames = '';
        $driversPhones = '';
        if (!empty($transfer->driver_ids)) {
            $drivers = Driver::query()->find($transfer->driver_ids);
            $driversNames = $drivers->map(fn($driver) => $driver->name)->join(', ');
            $driversPhones = $drivers->map(fn($driver) => $driver->phone)->join(', ');
        }

        $placeholders = [
            'transfer_number' => 1000 + $transfer->id,
            'created_at' => Carbon::parse($transfer->date_time)->format('d-M Y H:i'),
            'route' => $transfer->route,
            'driver' => $driversNames,
            'driver_phone' => $driversPhones,
            'passenger' => $transfer->nameplate,
            'mark' => $transfer->mark,
            'transport_type' => $transfer->transport_type->getLabel(),
            'pickup_location' => $transfer->place_of_submission,
            'company' => $transfer->company->name,
            'comment' => $transfer->comment,
            'pax' => $transfer->pax,
        ];
        foreach ($placeholders as $placeholder => $value) {
            $templateProcessor->setValue($placeholder, $value);
        }

        return $templateProcessor;
    }
}
