<?php

namespace App\Filament\Resources\HotelSalesResource\Pages;

use Filament\Actions;
use App\Models\Hotel;
use App\Models\Company;
use Illuminate\Support\Arr;
use App\Enums\CurrencyEnum;
use App\Services\TourService;
use App\Enums\RoomPersonType;
use App\Services\ExpenseService;
use Filament\Resources\Pages\ListRecords;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Database\Eloquent\Collection;
use App\Filament\Resources\HotelSalesResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListHotels extends ListRecords
{
    protected static string $resource = HotelSalesResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_all')
                ->label('Export All')
                ->icon('heroicon-o-document-text')
                ->requiresConfirmation(true)
                ->action(fn() => $this->exportExpenses()),
        ];
    }
    
    public function exportExpenses(): StreamedResponse
    {
        $filters = $this->table->getFiltersForm()->getState(); // Get current filters
        $filters = Arr::get($filters, 'filters', []);
        
        $currency = $filters['currency'] ?? CurrencyEnum::UZS->value;
        $isUsd = $currency == CurrencyEnum::USD->value;
        $currencySymbol = $isUsd ? CurrencyEnum::USD->getSymbol() : CurrencyEnum::UZS->getSymbol();
        
        $companyId = $filters['company_id'] ?? null;
        $group = null;
        if ($companyId) {
            /** @var Company $company */
            $company = Company::query()->where('id', $companyId)->first();
            $group = $company->group;
        }
        
        /** @var Collection<Hotel> $hotels */
        $hotels = Hotel::query()->get();
        
        $spreadsheet = new Spreadsheet();
        
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getDefaultColumnDimension()->setWidth(15);
        //        $sheet->setTitle($typeLabel);
        
        $headers = [
            'name' => 'Name',
            'email' => 'Email',
            'room_type' => 'Room Type',
            'season_type' => 'Season Type',
            'price_uz' => 'Price Uz',
            'price_foreign' => 'Price Foreign',
        ];
        
        $headerLabels = array_values($headers);
        $sheet->fromArray($headerLabels, null, 'A1');
        
        $rowIndex = 2;
        foreach ($hotels as $hotel) {
            $startRow = $rowIndex;
            
            foreach ($hotel->roomTypes as $roomType) {
                $row = [];
                $row['name'] = $hotel->name;
                $row['email'] = $hotel->email;
                $row['room_type'] = $roomType->roomType->name;
                $row['season_type'] = $roomType->season_type->getLabel();
                
                $price = $roomType->getPriceByGroup($group, RoomPersonType::Uzbek);
                $priceForeign = $roomType->getPriceByGroup($group, RoomPersonType::Foreign);
                
                $row['price_uz'] = TourService::formatMoney(ExpenseService::getPrice($price, $isUsd)) . ' ' . $currencySymbol;
                $row['price_foreign'] = TourService::formatMoney(ExpenseService::getPrice($priceForeign, $isUsd)) . ' ' . $currencySymbol;
                
                $sheet->fromArray($row, null, "A{$rowIndex}");
                $rowIndex++;
            }
            
            // Merge hotel name and email cells for visual grouping
            if ($startRow < $rowIndex) {
                $sheet->mergeCells("A{$startRow}:A" . ($rowIndex - 1));
                $sheet->mergeCells("B{$startRow}:B" . ($rowIndex - 1));
                
                // Center the merged cells vertically and horizontally
                $style = $sheet->getStyle("A{$startRow}:B" . ($rowIndex - 1));
                $style->getAlignment()
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }
        }
        
        // Auto-size columns for better readability
        foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Add header styling
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0']
            ]
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestDataColumn() . '1')->applyFromArray($headerStyle);
        
        // Add borders to all cells with data
        $lastRow = $rowIndex - 1;
        $lastCol = $sheet->getHighestDataColumn();
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
        
        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function() use ($writer) {
            $writer->save('php://output');
        });
        
        $filename = 'hotel_sales.xlsx';
        
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');
        
        return $response;
    }
}
