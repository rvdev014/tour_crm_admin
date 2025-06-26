<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\Transfer;
use App\Services\ExportClientService;
use App\Services\ExportHotelService;
use App\Services\ExportMuseumService;
use App\Services\ExportService;
use App\Services\ExportTransferService;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ExportController extends Controller
{
    /**
     * @throws Exception
     */
    public function export(Tour $tour): BinaryFileResponse
    {
        $spreadsheet = ExportService::getExport($tour);

        $filename = "tour_" . $tour->group_number . "_client.xlsx";
        $tempFile = tempnam(sys_get_temp_dir(), $filename);

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        register_shutdown_function(fn() => File::deleteDirectory($tempFile));

        return response()->download($tempFile, $filename)->deleteFileAfterSend();
    }

    /**
     * @throws Exception
     */
    public function exportClient(Tour $tour): BinaryFileResponse
    {
        $spreadsheet = ExportClientService::getExport($tour);

        $filename = "tour_" . $tour->group_number . "_client.xlsx";
        $tempFile = tempnam(sys_get_temp_dir(), $filename);

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        register_shutdown_function(fn() => File::deleteDirectory($tempFile));

        return response()->download($tempFile, $filename)->deleteFileAfterSend();
    }

    /**
     * @throws Exception
     */
    public function exportMuseum(Tour $tour): BinaryFileResponse
    {
        $tempFile = ExportMuseumService::getMuseumReportFile($tour);

        register_shutdown_function(fn() => File::deleteDirectory($tempFile));

        return response()->download($tempFile)->deleteFileAfterSend();
    }

    public function exportHotelsZip(Tour $tour): BinaryFileResponse
    {
        $tempDir = ExportService::getTempDir("hotel_reports");

        $hotelsData = ExportHotelService::getHotelsData($tour);
        foreach ($hotelsData as $hotelItem) {
            ExportHotelService::saveReport($hotelItem, $tempDir);
        }

        $zip = new ZipArchive();
        $zipFilename = "Tour_" . $tour->group_number . "_hotels.zip";
        $zipPath = ExportService::getTempDir("hotel_reports") . "/Tour_" . $tour->group_number . "_hotels.zip";
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach (glob(ExportService::getTempDir("hotel_reports") . "/*.docx") as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        } else {
            die('Failed to create ZIP');
        }

        register_shutdown_function(fn() => File::deleteDirectory($tempDir));

        return response()->download($zipPath, $zipFilename)->deleteFileAfterSend();
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException|Exception
     */
    public function exportAllZip(Tour $tour): BinaryFileResponse
    {
        $tempDir = ExportService::getTempDir("all_reports");

        $hotelsData = ExportHotelService::getHotelsData($tour);
        foreach ($hotelsData as $hotelItem) {
            $hotelName = str_replace([' ', '(', ')'], '_', $hotelItem['hotelName']);

            $fileName = ExportService::getTempDir("all_reports/hotels") . '/' . $hotelName . '.docx';
            $templateProcessor = ExportHotelService::getReplacedTemplateFirst($hotelItem);
            $templateProcessor->saveAs($fileName);

            $fileName = ExportService::getTempDir("all_reports/client_vouchers") . '/' . $hotelName . '.docx';
            $templateProcessor = ExportHotelService::getReplacedTemplateSecond($hotelItem);
            $templateProcessor->saveAs($fileName);
        }

        $zip = new ZipArchive();
        $zipFilename = "Tour_" . $tour->group_number . "_full.zip";
        $zipPath = $tempDir . "/$zipFilename";

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Main report
            $spreadsheet = ExportService::getExport($tour);
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($filePath = $tempDir . '/Tour_' . $tour->group_number . '_main.xlsx');
            $zip->addFile($filePath, 'Main_report.xlsx');

            // Client report
            $spreadsheet = ExportClientService::getExport($tour);
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($filePath = $tempDir . '/Tour_' . $tour->group_number . '_client.xlsx');
            $zip->addFile($filePath, 'Client_report.xlsx');

            // Museum report
            $spreadsheet = ExportMuseumService::getExport($tour);
            if ($spreadsheet) {
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save($filePath = $tempDir . '/Tour_' . $tour->group_number . '_museum.xlsx');
                $zip->addFile($filePath, 'Museum_report.xlsx');
            }

            // Add Hotels folder
            $zip->addEmptyDir('Hotels');
            foreach (glob($tempDir . "/hotels/*.docx") as $file) {
                $zip->addFile($file, 'Hotels/' . basename($file));
            }

            // Add Hotels folder
            $zip->addEmptyDir('Client Vouchers');
            foreach (glob($tempDir . "/client_vouchers/*.docx") as $file) {
                $zip->addFile($file, 'Client Vouchers/' . basename($file));
            }

            $zip->close();
        } else {
            die('Failed to create ZIP');
        }

        register_shutdown_function(fn() => File::deleteDirectory($tempDir));

        return response()->download($zipPath, $zipFilename)->deleteFileAfterSend();
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public function exportTransfer(Transfer $transfer): BinaryFileResponse
    {
        $tempDir = ExportService::getTempDir("transfer_reports");

        $fileName = $tempDir . '/Transfer_' . $transfer->id . '.docx';
        $templateProcessor = ExportTransferService::getReplacedTemplateForTransfer($transfer);
        $templateProcessor->saveAs($fileName);

        register_shutdown_function(fn() => File::deleteDirectory($tempDir));

        return response()->download($fileName, 'Transfer_' . $transfer->id . '.docx')->deleteFileAfterSend(true);
    }
}
