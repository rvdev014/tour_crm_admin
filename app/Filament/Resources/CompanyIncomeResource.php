<?php

namespace App\Filament\Resources;

use App\Enums\CompanyType;
use App\Enums\CurrencyEnum;
use App\Enums\PaymentStatus;
use App\Enums\TourType;
use App\Filament\Resources\CompanyIncomeResource\Pages;
use App\Filament\Resources\CompanyIncomeResource\RelationManagers;
use App\Models\Company;
use App\Models\Tour;
use App\Models\TourDayExpense;
use App\Services\TourService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CompanyIncomeResource extends Resource
{
    protected static ?string $model = Tour::class;
    protected static ?string $label = 'Company Incomes';

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
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
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
                                ->options(fn() => Company::all()->pluck('name', 'id')),

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
                    ->query(function (Builder $query, $data) {
                        if ($companyIds = $data['companies']) {
                            $query = $query->whereIn('company_id', $companyIds);
                        }
                        if ($tourType = $data['tour_type']) {
                            $query = $query->where('type', $tourType);
                        }
                        if ($paymentStatus = $data['payment_status']) {
                            $query = $query->where('payment_status', $paymentStatus);
                        }
                        if ($data['date_from']) {
                            $query = $query->whereDate('created_at', '>=', $data['date_from']);
                        }
                        if ($data['date_until']) {
                            $query = $query->whereDate('created_at', '<=', $data['date_until']);
                        }
                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $query = Tour::query();

                        $indicators = [];
                        if ($companyIds = $data['companies']) {
                            $query = $query->whereIn('company_id', $companyIds);
                            $companies = Company::query()->whereIn('id', $data['companies'])->get();
                            $companyNames = $companies->map(fn($company) => $company->name)->join(', ');
                            $indicators['company_id'] = $companyNames . " ({$query->count()})";
                        }

                        return $indicators;
                    })
            ], layout: FiltersLayout::AboveContent)
            ->columns([
                Tables\Columns\TextColumn::make('group_number')
                    ->label('Group number')
                    ->getStateUsing(function (Tour $record) {
                        if ($record->isCorporate()) {
                            $link = "/admin/tour-corporate/$record->id/edit";
                        } else {
                            $link = "/admin/tour-tps-test/$record->id/edit";
                        }
                        return "<a href='{$link}' target='_blank'>$record->group_number</a>";
                    })
                    ->color('info')
                    ->html(),

                Tables\Columns\TextColumn::make('company')
                    ->getStateUsing(function (Tour $record) {
                        return $record->company->name;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('inn')
                    ->label('Company Inn')
                    ->getStateUsing(function (Tour $record) {
                        return $record->company->inn;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('tour_pax')
                    ->label('Pax')
                    ->getStateUsing(function (Tour $record) {
                        return $record->getTotalPax();
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(function (Tour $record) {
                        if ($record->isCorporate()) {
                            $price = $record->expenses_total;
                        } else {
                            $price = $record->total_price;
                        }
                        return TourService::formatMoney($price) . ' ' . CurrencyEnum::UZS->getSymbol();
                    })
                    ->label('Price')
                    ->searchable(),

                Tables\Columns\SelectColumn::make('payment_status')
                    ->options(PaymentStatus::class),
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
