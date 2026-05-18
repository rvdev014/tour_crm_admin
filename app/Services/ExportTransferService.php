<?php

namespace App\Services;

use App\Models\Transfer;
use Carbon\Carbon;
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

        $placeholders = [
            'transfer_number' => 1000 + $transfer->id,
            'date_time' => Carbon::parse($transfer->date_time)->format('d-M Y H:i'),
            'route' => $transfer->route,
            'driver' => $transfer->driver_name ?? '',
            'driver_phone' => $transfer->driver_phone ?? '',
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
