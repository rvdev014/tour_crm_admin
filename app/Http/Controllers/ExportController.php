<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\Transfer;
use App\Services\ExportClientService;
use App\Services\ExportHotelService;
use App\Services\ExportMuseumService;
use App\Services\ExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
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

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * @throws Exception
     */
    public function exportMuseum(Tour $tour): BinaryFileResponse
    {
        $spreadsheet = ExportMuseumService::getExport($tour);

        $filename = "tour_" . $tour->group_number . "_museum.xlsx";
        $tempFile = tempnam(sys_get_temp_dir(), $filename);

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public function exportHotelsZip(Tour $tour): void
    {
        $tempDir = $this->getTempDir("hotel_reports");

        $hotelsData = ExportHotelService::getHotelsData($tour);
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
     * @throws CreateTemporaryFileException|Exception
     */
    public function exportAllZip(Tour $tour): void
    {
        $tempDir = $this->getTempDir("all_reports");

        $hotelsData = ExportHotelService::getHotelsData($tour);
        foreach ($hotelsData as $hotelItem) {
            $hotelName = str_replace([' ', '(', ')'], '_', $hotelItem['hotelName']);
            $fileName = $this->getTempDir("all_reports/hotels") . '/' . $hotelName . '.docx';

            $templateProcessor = ExportHotelService::getReplacedTemplate($hotelItem);
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

        // redirect to back
        redirect()->back();
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public function exportTransfer(Transfer $transfer): void
    {
        $tempDir = $this->getTempDir("transfer_reports");

        $fileName = $tempDir . '/Transfer_' . $transfer->id . '.docx';
        $templateProcessor = ExportHotelService::getReplacedTemplateForTransfer($transfer);
        $templateProcessor->saveAs($fileName);

        // Output file for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="Transfer_' . $transfer->id . '.docx"');
        header('Content-Length: ' . filesize($fileName));
        readfile($fileName);

        // Clean up temporary files
        unlink($fileName); // Delete file
        rmdir($tempDir); // Delete directory
    }

    protected function getTempDir(string $dirName): string
    {
//        $tempDir = sys_get_temp_dir() . '/' . $dirName;
        $tempDir = storage_path('app/temp/' . $dirName);
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        return $tempDir;
    }
}
