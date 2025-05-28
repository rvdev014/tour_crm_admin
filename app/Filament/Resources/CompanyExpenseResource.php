<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Enums\TourType;
use App\Models\Company;
use Filament\Forms\Form;
use App\Enums\CompanyType;
use App\Enums\ExpenseType;
use Filament\Tables\Table;
use App\Enums\PaymentStatus;
use App\Services\TourService;
use App\Models\CompanyExpense;
use App\Models\TourDayExpense;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CompanyExpenseResource\Pages;
use App\Filament\Resources\CompanyExpenseResource\RelationManagers;

class CompanyExpenseResource extends Resource
{
    protected static ?string $model = TourDayExpense::class;
    protected static ?string $label = 'Company Expenses';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\Filter::make('company')
                    ->columnSpanFull()
                    ->form([
                        Forms\Components\Grid::make(6)->schema([
                            Forms\Components\Select::make('tour_type')
                                ->native(false)
                                ->label('Tour Type')
                                ->options([
                                    TourType::TPS->value => TourType::TPS->getLabel(),
                                    TourType::Corporate->value => TourType::Corporate->getLabel(),
                                ]),

                            Forms\Components\Select::make('companies')
                                ->native(false)
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->label('Company')
                                ->options(
                                    fn() => Company::all()
                                        ->where('type', CompanyType::Corporate)
                                        ->pluck('name', 'id')
                                ),

                            Forms\Components\Select::make('expense_types')
                                ->native(false)
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(ExpenseType::class),

                            Forms\Components\Select::make('payment_status')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(PaymentStatus::class),

                            Forms\Components\DatePicker::make('date_from')
                                ->displayFormat('d.m.Y')
                                ->native(false),

                            Forms\Components\DatePicker::make('date_until')
                                ->displayFormat('d.m.Y')
                                ->native(false),
                        ])
                    ])
                    ->query(function(Builder $query, $data) {
                        if ($companyIds = $data['companies']) {
                            $query = $query->where(function($query) use ($companyIds) {
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
                        if ($tourType = $data['tour_type']) {
                            $query = $query
                                ->whereHas(
                                    'tourGroup',
                                    fn($q) => $q->whereHas('tour', fn($q) => $q->where('type', $tourType))
                                )
                                ->orWhereHas(
                                    'tourDay',
                                    fn($q) => $q->whereHas('tour', fn($q) => $q->where('type', $tourType))
                                );
                        }
                        if ($data['expense_types']) {
                            $query = $query->whereIn('type', $data['expense_types']);
                        }
                        if ($paymentStatus = $data['payment_status']) {
                            $query = $query
                                ->whereHas(
                                    'tourGroup',
                                    fn($q) => $q->whereHas('tour', fn($q) => $q->where('payment_status', $paymentStatus)
                                    )
                                )
                                ->orWhereHas(
                                    'tourDay',
                                    fn($q) => $q->whereHas('tour', fn($q) => $q->where('payment_status', $paymentStatus)
                                    )
                                );
                        }
                        if ($data['date_from']) {
                            $query = $query->where(function($subQuery) use ($data) {
                                $subQuery
                                    ->whereDate('date', '>=', $data['date_from'])
                                    ->orWhereHas('tourDay', function($q) use ($data) {
                                        $q->whereDate('date', '>=', $data['date_from']);
                                    });
                            });
                        }
                        if ($data['date_until']) {
                            $query = $query->where(function($subQuery) use ($data) {
                                $subQuery
                                    ->whereDate('date', '<=', $data['date_until'])
                                    ->orWhereHas('tourDay', function($q) use ($data) {
                                        $q->whereDate('date', '<=', $data['date_until']);
                                    });
                            });
                        }
                        return $query;
                    })
                    ->indicateUsing(function(array $data): array {
                        $query = TourDayExpense::query();

                        $indicators = [];
                        if ($companyIds = $data['companies']) {
                            $query = $query->where(function($query) use ($companyIds) {
                                $query
                                    ->whereHas('tour', fn($q) => $q->whereIn('company_id', $companyIds))
                                    ->orWhereHas(
                                        'tourDay',
                                        fn($q) => $q->whereHas('tour', fn($q) => $q->whereIn('company_id', $companyIds))
                                    );
                            });
                            $companies = Company::query()->whereIn('id', $data['companies'])->get();
                            $companyNames = $companies->map(fn($company) => $company->name)->join(', ');
                            $indicators['company_id'] = $companyNames . " ({$query->count()})";
                        }

                        if ($data['expense_types']) {
                            $query = $query->whereIn('type', $data['expense_types']);
                            $expenseTypes = collect($data['expense_types'])->map(
                                fn($expenseType) => ExpenseType::from($expenseType)->getLabel()
                            )->join(', ');
                            $indicators['statuses'] = 'Status: ' . $expenseTypes . " ({$query->count()})";
                        }

                        return $indicators;
                    })
            ], layout: FiltersLayout::AboveContent)
            ->columns([
                Tables\Columns\TextColumn::make('group_number')
                    ->label('Group number')
                    ->getStateUsing(function(TourDayExpense $record) {
                        $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                        if ($tour->isCorporate()) {
                            $link = "/admin/tour-corporate/$tour->id/edit";
                        } else {
                            $link = "/admin/tour-tps-test/$tour->id/edit";
                        }
                        return "<a href='{$link}' target='_blank'>$tour->group_number</a>";
                    })
                    ->color('info')
                    ->html(),

                Tables\Columns\TextColumn::make('company')
                    ->getStateUsing(function(TourDayExpense $record) {
                        $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                        return $tour->company->name;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('inn')
                    ->label('Company Inn')
                    ->getStateUsing(function(TourDayExpense $record) {
                        $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                        return $tour->company->inn;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('expense_date')
                    ->getStateUsing(function(TourDayExpense $record) {
                        $date = $record->tourDay->date ?? $record->date;
                        return $date->format('d/m/Y');
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('expense_type')
                    ->getStateUsing(function(TourDayExpense $record) {
                        return $record->type->getLabel();
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('expense_name')
                    ->label('Expense Name')
                    ->getStateUsing(function(TourDayExpense $record) {
                        return match ($record->type) {
                            ExpenseType::Hotel                      => $record->hotel?->name,
                            ExpenseType::Museum                     => TourService::getMuseumsByIds([1, 2])->values(
                            )->join(', '),
                            ExpenseType::Lunch, ExpenseType::Dinner => $record->restaurant?->name,
                            ExpenseType::Train                      => $record->train?->name,
                            ExpenseType::Show                       => $record->show?->name,
                            default                                 => '',
                        };
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('tour_pax')
                    ->label('Pax')
                    ->getStateUsing(function(TourDayExpense $record) {
                        $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                        if ($record->tourGroup) {
                            return $record->tourGroup->passengers()->count();
                        }
                        return $tour->getTotalPax();
                    }),

                Tables\Columns\TextColumn::make('route')
                    ->label('Route')
                    ->getStateUsing(function(TourDayExpense $record) {
                        $fromCity = $record->tourDay?->city?->name ?? $record->city?->name;

                        return match ($record->type) {
                            ExpenseType::Transport => $record->transport_route,
                            ExpenseType::Flight     => $record->plane_route,
                            ExpenseType::Train     => "$fromCity - {$record->toCity?->name}",
                            default                => '',
                        };
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(function(TourDayExpense $record) {
                        return TourService::formatMoney($record->price) . ' ' . ($record->price_currency?->getSymbol(
                            ) ?? '$');
                    })
                    ->label('Price')
                    ->searchable(),

                /*Tables\Columns\TextColumn::make('payment_status')
                    ->getStateUsing(function(TourDayExpense $record) {
                        return $record->payment_status?->getLabel();
                    }),*/

                Tables\Columns\SelectColumn::make('payment_status')
                    ->options(PaymentStatus::class),

                Tables\Columns\TextColumn::make('passengers')
                    ->label('Passengers FIO')
                    ->getStateUsing(function(TourDayExpense $record) {
                        $passengers = [];
                        if ($record->tourGroup) {
                            foreach ($record->tourGroup->passengers as $passenger) {
                                $passengers[] = $passenger->name;
                            }
                        }
                        return collect($passengers)->join(', ');
                    })
                    //                    ->width('300px')
                    //                    ->wrap()
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanyExpenses::route('/'),
            'create' => Pages\CreateCompanyExpense::route('/create'),
            'edit' => Pages\EditCompanyExpense::route('/{record}/edit'),
        ];
    }
}
