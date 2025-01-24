<?php

namespace App\Services;

use App\Models\Tour;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportMuseumService
{
    public static function getExport(Tour $tour): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');
        $spreadsheet->getDefaultStyle()->getFont()->setSize(9);
        $spreadsheet->getDefaultStyle()->getFont()->setBold(true);
        $spreadsheet->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $spreadsheet->getDefaultStyle()->getAlignment()->setWrapText(true);

        $sheet = self::genTable($sheet, 1, 'J');

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public static function genTable(Worksheet $sheet, $startRow, $endColumn): Worksheet
    {
        $textContent = "EAST ASIA POINT TRAVEL & TOURS\n115A, Buyuk Ipak Yoli Street, 100077 Tashkent, Uzbekistan\nPhone/Fax: +99871 268 77 52 E-mail: info@asia-point.uz";
        $sheet->setCellValue("A$startRow", $textContent);
        $sheet->mergeCells("A$startRow:$endColumn" . $startRow);

        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');
        $drawing->setPath(__DIR__ . '/Templates/Images/logo.jpg'); // Replace with the path to your image
        $drawing->setCoordinates("I$startRow"); // Place the image in column H
        $drawing->setOffsetX(10); // Adjust the X offset if needed
        $drawing->setOffsetY(10); // Adjust the Y offset if needed
        $drawing->setWidth(95); // Set the width of the image
        $drawing->setHeight($imgHeight = 55); // Set the height of the image to match the row height
        $drawing->setWorksheet($sheet);


        // Second row
        $secondRow = $startRow + 1;
        $sheet->mergeCells("A$secondRow:F$secondRow");
        $sheet->setCellValue("A$secondRow", 'МУЗЕЙ ЗАПОВЕДНИК «ИЧАН КАЛЪА»');
        $sheet->setCellValue("I$secondRow", 'Дата:');
        $sheet->setCellValue("J$secondRow", '10/3/2018');
        $sheet->getStyle("A$secondRow:J$secondRow")->getFont()->setSize(11);

        $sheet->getRowDimension($startRow)->setRowHeight($imgHeight); // Set the row height to match the image height

        return $sheet;
    }
}
