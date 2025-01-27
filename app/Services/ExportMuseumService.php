<?php

namespace App\Services;

use App\Enums\ExpenseType;
use App\Models\Museum;
use App\Models\MuseumItem;
use App\Models\Tour;
use App\Models\TourDay;
use App\Models\TourDayExpense;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
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

        $tableStartRow = 0;
        $museumsData = self::getMuseums($tour);
        foreach ($museumsData as $museumData) {
            $sheet = self::genTable($sheet, $museumData, $tableStartRow + 1, 'J');
            $tableStartRow = $sheet->getHighestRow();
        }

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public static function genTable(Worksheet $sheet, array $museumData, $startRow, $endColumn): Worksheet
    {
        $currentRow = $startRow;

        ////////////////////// HEADER //////////////////////
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

        $sheet->getRowDimension($startRow)->setRowHeight($imgHeight); // Set the row height to match the image height

        $currentRow = $startRow + 1;
        $sheet->mergeCells("A$currentRow:F$currentRow");
        $museumName = $museumData['museum'];
        $sheet->setCellValue("A$currentRow", "МУЗЕЙ «{$museumName}»");
        $sheet->getRowDimension($currentRow)->setRowHeight(15);
        $sheet->getStyle("A$currentRow")->getFont()->setItalic(true);
        $sheet->setCellValue("I$currentRow", 'Дата:');
        $sheet->setCellValue("J$currentRow", '10/3/2018');
        $sheet->getStyle("A$currentRow:J$currentRow")->getFont()->setSize(11);


        ///////////////// FIRST ROW //////////////////////
        $currentRow++;
        $sheet->setCellValue("A$currentRow", 'Название объекта');
        $sheet->mergeCells("A$currentRow:B$currentRow");

        $sheet->setCellValue("C$currentRow", '№ группы');
        $sheet->setCellValue("D$currentRow", 'Страна');
        $sheet->setCellValue("E$currentRow", "Дата посещений");

        $sheet->setCellValue("F$currentRow", '№ договора');
        $sheet->mergeCells("F$currentRow:G$currentRow");

        $sheet->setCellValue("H$currentRow", "Кол—во\nтуристов");

        $sheet->setCellValue("I$currentRow", "Ответственный\nтур.оператор");
        $sheet->mergeCells("I$currentRow:J$currentRow");

        $sheet->getStyle("A$currentRow:J$currentRow")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A$currentRow:J$currentRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        ///////////////// SECOND ROW //////////////////////
        $currentRow++;
        $sheet->setCellValue("A$currentRow", $museumData['museum']);
        $sheet->mergeCells("A$currentRow:B$currentRow");

        $sheet->setCellValue("C$currentRow", $museumData['group_number']);
        $sheet->setCellValue("D$currentRow", $museumData['country']);
        $sheet->setCellValue("E$currentRow", $museumData['date']);

        $sheet->setCellValue("F$currentRow", $museumData['contract_number']);
        $sheet->mergeCells("F$currentRow:G$currentRow");

        $sheet->setCellValue("H$currentRow", $museumData['tourists_count']);

        $sheet->setCellValue("I$currentRow", $museumData['tour_operator']);
        $sheet->mergeCells("I$currentRow:J$currentRow");

        $sheet->getStyle("A$currentRow:J$currentRow")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A$currentRow:J$currentRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($currentRow)->setRowHeight(30);

        ///////////////// FOOTER //////////////////////
        $currentRow++;
        $sheet->mergeCells("A$currentRow:J$currentRow");
        $sheet->setCellValue("A$currentRow", "Семейное предприятие «EAST ASIA POINT»\nР/счет 20208000205000278001\nТашкентский областной филиал Банка  “Асака»      МФО  00411    ИНН 207160718       ОКЭД 79900");
        $sheet->getStyle("A$currentRow")->getFont()->setItalic(true);
        $sheet->getRowDimension($currentRow)->setRowHeight(45);

        $sheet->getStyle('A' . $startRow + 2 . ':J' . $startRow + 3)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return $sheet;
    }

    public static function getMuseums(Tour $tour): \Illuminate\Support\Collection
    {
        $result = collect();
        foreach ($tour->days as $tourDay) {
            /** @var Collection<TourDayExpense> $museumExpenses */
            $museumExpenses = $tourDay->expenses->filter(
                fn(TourDayExpense $expense) => $expense->type == ExpenseType::Museum
            );

            foreach ($museumExpenses as $museumExpense) {
                if (!empty($museumExpense->museum_item_ids)) {
                    $museumItems = MuseumItem::query()->whereIn('id', $museumExpense->museum_item_ids)->get();
                    self::collectMuseums($result, $tour, $tourDay, $museumItems);
                } else {
                    $museums = Museum::query()->whereIn('id', $museumExpense->museum_ids)->get();
                    self::collectMuseums($result, $tour, $tourDay, $museums);
                }
            }
        }

        return $result;
    }

    public static function collectMuseums(
        \Illuminate\Support\Collection &$result,
        Tour $tour,
        TourDay $tourDay,
        $museums
    ): void {
        $date = $tourDay->date->format('d.m.Y');

        foreach ($museums as $museum) {
            $alreadyExists = $result->first(
                fn($item) => $item['date'] == $date && $item['museum'] == $museum->name
            );
            if (!$alreadyExists) {
                $result->push([
                    'museum' => $museum->name,
                    'group_number' => $tour->group_number ?? '-',
                    'country' => $tour->country->name ?? '-',
                    'date' => $date ?? '-',
                    'contract_number' => $museum->contract ?? '-',
                    'tourists_count' => $tour->getTotalPax(),
                    'tour_operator' => "{$tour->createdBy->name}\n{$tour->createdBy->email}",
                ]);
            }
        }
    }
}
