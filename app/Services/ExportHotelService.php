<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\TourRoomType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\TemplateProcessor;

class ExportHotelService
{
    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public static function getExport(Tour $tour): TemplateProcessor
    {
        $templateProcessor = new TemplateProcessor(__DIR__ . '/Templates/Report_hotel.docx');

        $templateProcessor->setValue('{groupNum}', $tour->group_number);
        $templateProcessor->setValue('{pax}', $tour->getTotalPax());

        $rooming = $tour->roomTypes->mapWithKeys(fn(TourRoomType $roomType) => [
            $roomType->roomType->name => $roomType->amount
        ]);
        $templateProcessor->setValue('{rooming}', $rooming->map(fn($amount, $roomType) => "$roomType: $amount")->implode("\t\t"));
        $templateProcessor->setValue('{country}', $tour->country->name);


        return $templateProcessor;
    }
}
