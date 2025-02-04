<?php

namespace App\Http\Controllers;

use App\Enums\TourType;
use App\Models\Tour;
use App\Services\ExportClientService;
use App\Services\ExportMuseumService;
use App\Services\ExportHotelService;
use App\Services\ExportService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

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
    public function exportHotelsZip(Tour $tour): void
    {
        $tempDir = $this->getTempDir("hotel_reports");

        if ($tour->type === TourType::Corporate) {
            $hotelsData = ExportHotelService::getHotelsDataCorporate($tour);
        } else {
            $hotelsData = ExportHotelService::getHotelsData($tour);
        }
        foreach ($hotelsData as $hotelItem) {
            $hotelName = str_replace([' ', '(', ')'], '_', $hotelItem['hotelName']);
            $fileName = $tempDir . '/Hotel_' . $hotelName . '.docx';

            $templateProcessor = ExportHotelService::getReplacedTemplate($hotelItem);
            $templateProcessor->saveAs($fileName);
        }

        $zip = new ZipArchive();
        $zipFilename = "Tour_" . $tour->group_number . "_hotels.zip";
        $zipPath = $this->getTempDir("hotel_reports") . "/Tour_" . $tour->group_number . "_hotels.zip";
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach (glob($this->getTempDir("hotel_reports") . "/*.docx") as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        } else {
            die('Failed to create ZIP');
        }

        // Output ZIP for download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);

        // Clean up temporary files
        array_map('unlink', glob($tempDir . '/*')); // Delete files
        rmdir($tempDir); // Delete directory
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public function exportHotelSingle(Tour $tour): void
    {
        $hotelsData = ExportHotelService::getHotelsData($tour);
        $hotelsData = $hotelsData->toArray();
        $hotelItem = reset($hotelsData);
        $hotelName = str_replace([' ', '(', ')'], '_', $hotelItem['hotelName']);
        $fileName = 'Hotel_' . $hotelName . '.docx';

        $templateProcessor = ExportHotelService::getReplacedTemplate($hotelItem);

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $templateProcessor->saveAs('php://output');
    }


    protected function getTempDir(string $dirName): string
    {
        $tempDir = sys_get_temp_dir() . '/' . $dirName;
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        return $tempDir;
    }
}
