<?php

namespace App\Services;

use App\Enums\TransportType;
use App\Models\Transfer;
use Carbon\Carbon;
use PhpOffice\PhpWord\TemplateProcessor;

class ExportTransferService
{
    public static function generate(Transfer $transfer): TemplateProcessor
    {
        $templateProcessor = new TemplateProcessor(self::getTemplatePath());

        foreach (self::getPlaceholders($transfer) as $placeholder => $value) {
            $templateProcessor->setValue($placeholder, $value);
        }

        return $templateProcessor;
    }

    public static function getTemplatePath(): string
    {
        return __DIR__.'/Templates/Transfer_voucher.docx';
    }

    public static function getPlaceholders(Transfer $transfer): array
    {
        return [
            'transfer_number' => (string) (1000 + $transfer->id),
            'date_time' => $transfer->date_time ? Carbon::parse($transfer->date_time)->format('d M Y  H:i') : '-',
            'route' => $transfer->route ?: '-',
            'driver' => $transfer->driver_name ?: '-',
            'driver_phone' => $transfer->driver_phone ?: '-',
            'passenger' => $transfer->nameplate ?: '-',
            'company' => $transfer->company?->name ?? '-',
            'pax' => (string) ($transfer->pax ?? '-'),
            'mark' => $transfer->mark ?: '-',
            'transport_type' => self::getTransportTypeLabel($transfer->transport_type),
            'pickup_location' => $transfer->place_of_submission ?: '-',
            'comment' => $transfer->comment ?: '-',
        ];
    }

    /**
     * transport_type is a plain string column: TPS stores a TransportType enum
     * value (e.g. "1"), Corporate stores a free-form TransportClass name. Try
     * the enum first, fall back to the raw string.
     */
    public static function getTransportTypeLabel(?string $transportType): string
    {
        if (! $transportType) {
            return '-';
        }

        return TransportType::tryFrom((int) $transportType)?->getLabel() ?? $transportType;
    }

    public static function save(TemplateProcessor $templateProcessor, string $filePath): void
    {
        $templateProcessor->saveAs($filePath);
    }
}
