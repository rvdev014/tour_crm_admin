<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Services\ExportClientService;
use App\Services\ExportMuseumService;
use App\Services\ExportHotelService;
use App\Services\ExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\TemplateProcessor;

class ExportController extends Controller
{
    public function export(Tour $tour): void
    {
        $spreadsheet = ExportService::getExport($tour);

        $filename = "Tour_" . $tour->group_number . ".xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    public function exportClient(Tour $tour): void
    {
        $spreadsheet = ExportClientService::getExport($tour);

        $filename = "Tour_" . $tour->group_number . "_client.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    public function exportMuseum(Tour $tour): void
    {
       $spreadsheet = ExportMuseumService::getExport($tour);

       $filename = "Tour_" . $tour->group_number . "_museum.xlsx";
       header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
       header('Content-Disposition: attachment; filename="' . $filename . '"');
       header('Cache-Control: max-age=0');

       $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
       $writer->save('php://output');
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public function exportHotel(Tour $tour): void
    {
// Load the template file
        $templateProcessor = new TemplateProcessor(__DIR__ . '/../../Services/Templates/Report_hotel.docx');

// Replace placeholders
        $templateProcessor->setValue('name', 'John Doe');
        $templateProcessor->setValue('order_id', '12345');

// Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="customized.docx"');
        header('Cache-Control: max-age=0');

// Save the updated document directly to the output stream
        $templateProcessor->saveAs('php://output');
    }
}
