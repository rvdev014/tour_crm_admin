<?php

namespace App\Filament\Resources\CompanyExpenseResource\Pages;

use App\Enums\CurrencyEnum;
use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Filament\Resources\CompanyExpenseResource;
use App\Models\Driver;
use App\Models\TourDayExpense;
use App\Services\TourService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCompanyExpenses extends ListRecords
{
    protected static string $resource = CompanyExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_all')
                ->label('Export All')
                ->icon('heroicon-o-document-text')
                ->requiresConfirmation(true)
                ->action(fn () => $this->exportExpenses()),
            ExportAction::make()
                ->requiresConfirmation()
                ->exports([
                    ExcelExport::make()
                        ->withFilename(fn () => $this->generateExportFilename())
                        ->fromTable()
                        ->withColumns([
                            Column::make('group_number')
                                ->heading('Group number')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                                    return $tour->group_number;
                                }),
                            Column::make('company')
                                ->heading('Company')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                                    return $tour->company->name;
                                }),
                            Column::make('inn')
                                ->heading('Company Inn')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                                    return $tour->company->inn;
                                }),
                            Column::make('start_date')
                                ->heading('Start Date')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                                    return $tour->start_date?->format('d.m.Y H:i');
                                }),
                            Column::make('expense_date')
                                ->heading('Expense Date')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    $date = $record->tourDay->date ?? $record->date;
                                    return $date->format('d.m.Y');
                                }),
                            Column::make('passengers')
                                ->heading('Passengers FIO')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    return $record->tourGroup?->passengers?->first()?->name ?? '-';
                                }),
                            Column::make('expense_type')
                                ->heading('Expense Type')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    return $record->type->getLabel();
                                }),
                            Column::make('expense_name')
                                ->heading('Expense Name')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    return match ($record->type) {
                                        ExpenseType::Hotel => $record->hotel?->name,
                                        ExpenseType::Museum => TourService::getMuseumsByIds([1, 2])->values()->join(', '),
                                        ExpenseType::Lunch, ExpenseType::Dinner => $record->restaurant?->name,
                                        ExpenseType::Train => $record->train?->name,
                                        ExpenseType::Show => $record->show?->name,
                                        default => '',
                                    };
                                }),
                            Column::make('tour_pax')
                                ->heading('Pax')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                                    if ($record->tourGroup) {
                                        return $record->tourGroup->passengers()->count();
                                    }
                                    return $tour->getTotalPax();
                                }),
                            Column::make('route')
                                ->heading('Route')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    $fromCity = $record->tourDay?->city?->name ?? $record->city?->name;
                                    return match ($record->type) {
                                        ExpenseType::Transport => $record->transport_route,
                                        ExpenseType::Flight => $record->plane_route,
                                        ExpenseType::Train => "$fromCity - {$record->toCity?->name}",
                                        default => '',
                                    };
                                }),
                            Column::make('price')
                                ->heading('Price')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    return TourService::formatMoney($record->price_result) . ' ' . CurrencyEnum::UZS->getSymbol();
                                }),
                            Column::make('payment_status')
                                ->heading('Payment Status')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    return $record->payment_status?->getLabel();
                                }),
                            Column::make('invoice_status')
                                ->heading('Invoice Status')
                                ->getStateUsing(function (TourDayExpense $record) {
                                    return $record->invoice_status?->getLabel();
                                }),
                        ])
                ]),
            Actions\CreateAction::make(),
        ];
    }

    public function exportExpenses(): StreamedResponse
    {
        $filters = $this->table->getFiltersForm()->getState(); // Get current filters
        $filters = Arr::get($filters, 'filters', []);

        /** @var Collection<TourDayExpense> $expenses */
        $query = TourDayExpense::query()
            ->with([
                'tourGroup' => fn($query) => $query->with([
                    'tour' => fn($q) => $q->with('company'),
                    'passengers'
                ]),
                'tourDay' => fn($query) => $query->with(['tour' => fn($q) => $q->with('company')]),
            ]);

        // Apply filters to the query
        if ($companyIds = $filters['companies'] ?? null) {
            $query = $query->where(function ($query) use ($companyIds) {
                $query
                    ->whereHas(
                        'tourGroup',
                        fn($q) => $q->whereHas('tour', fn($q) => $q->whereIn('company_id', $companyIds))
                    )
                    ->orWhereHas(
                        'tourDay',
                        fn($q) => $q->whereHas('tour', fn($q) => $q->whereIn('company_id', $companyIds))
                    );
            });
        }
        if ($tourType = $filters['tour_type'] ?? null) {
            $query = $query->where(function ($query) use ($tourType) {
                $query
                    ->whereHas(
                        'tourGroup',
                        fn($q) => $q->whereHas('tour', fn($q) => $q->where('type', $tourType))
                    )
                    ->orWhereHas(
                        'tourDay',
                        fn($q) => $q->whereHas('tour', fn($q) => $q->where('type', $tourType))
                    );
            });
        }
        if ($filters['expense_types'] ?? null) {
            $query = $query->whereIn('type', $filters['expense_types']);
        }
        if ($paymentStatus = $filters['payment_status'] ?? null) {
            $query = $query->where('payment_status', $paymentStatus);
        }
        if ($filters['date_from'] ?? null) {
            $query = $query->where(function ($subQuery) use ($filters) {
                $subQuery
                    ->whereDate('date', '>=', $filters['date_from'])
                    ->orWhereHas('tourDay', function ($q) use ($filters) {
                        $q->whereDate('date', '>=', $filters['date_from']);
                    });
            });
        }
        if ($filters['date_until'] ?? null) {
            $query = $query->where(function ($subQuery) use ($filters) {
                $subQuery
                    ->whereDate('date', '<=', $filters['date_until'])
                    ->orWhereHas('tourDay', function ($q) use ($filters) {
                        $q->whereDate('date', '<=', $filters['date_until']);
                    });
            });
        }
        
        /** @var Collection<TourDayExpense> $expenses */
        $expenses = $query->get();

        $spreadsheet = new Spreadsheet();

        $groupData = [];
        foreach ($expenses as $expense) {
            $groupData[$expense->type->value][] = $expense;
        }

        $headers = [
            'group_number' => 'Group Number',
            'company' => 'Company',
            'inn' => 'Company Inn',
            'start_date' => 'Start Date',
            'expense_date' => 'Expense Date',
            'hotel_checkin_time' => 'Check-in Time',
            'hotel_checkout_time' => 'Check-out Time',
            'passengers' => 'Passengers FIO',
            'expense_type' => 'Expense Type',
            'expense_name' => 'Expense Name',
            'tour_pax' => 'Pax',
            'route' => 'Route',
            'price' => 'Price',
            'payment_status' => 'Payment Status',
            'invoice_status' => 'Invoice Status',
        ];

        $headerLabels = array_values($headers);

        $sheetIndex = 0;
        foreach ($groupData as $type => $expenses) {
            $typeLabel = ExpenseType::tryFrom($type)?->getLabel() ?? 'Other';
            if (empty($expenses)) {
                continue;
            }

            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet($sheetIndex);
            $sheet->getDefaultColumnDimension()->setWidth(15);
            $sheet->setTitle($typeLabel);

            if ($type != ExpenseType::Hotel) {
                unset($headers['hotel_checkin_time'], $headers['hotel_checkout_time']);
                $headerLabels = array_values($headers);
            }
            
            $sheet->fromArray($headerLabels, null, 'A1');

            $values = [];
            foreach ($expenses as $expense) {
                $tour = $expense->tourGroup?->tour ?? $expense->tourDay->tour;
                $company = $tour->company;
                $date = $expense->tourDay->date ?? $expense->date;
                $fromCity = $expense->tourDay?->city?->name ?? $expense->city?->name;

                if ($expense->tourGroup) {
                    $pax = $expense->tourGroup->passengers()->count();
                } else {
                    $pax = $tour->getTotalPax();
                }

                $values[]['group_number']   = $tour->group_number;
                $values[]['company']        = $company?->name;
                $values[]['inn']            = $company?->inn;
                $values[]['start_date']     = $tour->start_date?->format('d.m.Y H:i');
                $values[]['expense_date']   = $date?->format('d.m.Y');
                
                if ($expense->type == ExpenseType::Hotel) {
                    $values[]['hotel_checkin_time']  = $expense->hotel_checkin_time;
                    $values[]['hotel_checkout_time'] = $expense->hotel_checkout_time;
                }
                
                $values[]['passengers']     = $expense->tourGroup?->passengers?->first()?->name ?? '-';
                $values[]['expense_type']   = $expense->type->getLabel();
                $values[]['expense_name']   = match ($expense->type) {
                    ExpenseType::Hotel => $expense->hotel?->name,
                    ExpenseType::Museum => TourService::getMuseumsByIds([1, 2])->values()->join(', '),
                    ExpenseType::Lunch, ExpenseType::Dinner => $expense->restaurant?->name,
                    ExpenseType::Train => $expense->train?->name,
                    ExpenseType::Show => $expense->show?->name,
                    default => '',
                };
                $values[]['tour_pax']       = $pax;
                $values[]['route']          = match ($expense->type) {
                    ExpenseType::Transport => $expense->transport_route,
                    ExpenseType::Flight => $expense->plane_route,
                    ExpenseType::Train => "$fromCity - {$expense->toCity?->name}",
                    default => '',
                };
                $values[]['price']          = TourService::formatMoney($expense->price_result) . ' ' . CurrencyEnum::UZS->getSymbol();
                $values[]['payment_status'] = $expense->payment_status?->getLabel();
                $values[]['invoice_status'] = $expense->invoice_status?->getLabel();
            }

            $sheet->fromArray($values, null, 'A2');
            $sheetIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $filename = $this->generateExportFilename($filters);

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    protected function generateExportFilename($filters = null): string
    {
        if (empty($filters)) {
            $filters = $this->table->getFiltersForm()->getState(); // Get current filters
            $filters = Arr::get($filters, 'filters', []);
        }

        $tourType = Arr::get($filters, 'tour_type');
        $tourType = $tourType ? TourType::tryFrom($tourType)?->getLabel() : null;

        $dateFrom = Arr::get($filters, 'date_from');
        $dateFrom = $dateFrom ? Carbon::parse($dateFrom)?->format('dmy') : null;

        $dateUntil = Arr::get($filters, 'date_until');
        $dateUntil = $dateUntil ? Carbon::parse($dateUntil)?->format('dmy') : null;

        $filenameParts = ['expenses'];
        if ($tourType) {
            $filenameParts[] = strtolower($tourType);
        }
        if ($dateFrom) {
            $filenameParts[] = $dateFrom;
        }
        if ($dateUntil) {
            $filenameParts[] = $dateUntil;
        }

        return implode('_', $filenameParts) . '.xlsx';
    }
}
