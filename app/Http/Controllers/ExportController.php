<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Services\ExportClientService;
use App\Services\ExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExportController extends Controller
{
    public function export(Tour $tour): void
    {
        $filename = "Tour_" . $tour->group_number . ".xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $spreadsheet = ExportService::getExport($tour);
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    public function exportClient(Tour $tour): void
    {
        $filename = "Tour_" . $tour->group_number . ".xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $spreadsheet = ExportClientService::getExport($tour);
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    }
}
