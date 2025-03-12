<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Filament\Resources\CompanyExpenseResource\Pages;
use App\Filament\Resources\CompanyExpenseResource\RelationManagers;
use App\Models\Company;
use App\Models\CompanyExpense;
use App\Models\TourDayExpense;
use App\Models\Transfer;
use App\Services\TourService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;

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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\Filter::make('company')
                    ->columnSpan(2)
                    ->form([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('companies')
                                ->native(false)
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->label('Company')
                                ->options(fn() => Company::all()->pluck('name', 'id')),
                            Forms\Components\Select::make('expense_types')
                                ->native(false)
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(ExpenseType::class),
                        ])
                    ])
                    ->query(function (Builder $query, $data) {
                        if ($companyIds = $data['companies']) {
                            $query = $query->where(function ($query) use ($companyIds) {
                                $query
                                    ->whereHas('tour', fn($q) => $q->whereIn('company_id', $companyIds))
                                    ->orWhereHas(
                                        'tourDay',
                                        fn($q) => $q->whereHas('tour', fn($q) => $q->whereIn('company_id', $companyIds))
                                    );
                            });
                        }
                        if ($data['expense_types']) {
                            $query = $query->whereIn('type', $data['expense_types']);
                        }
                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $query = TourDayExpense::query();

                        $indicators = [];
                        if ($companyIds = $data['companies']) {
                            $query = $query->where(function ($query) use ($companyIds) {
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
                    ->getStateUsing(function (TourDayExpense $record) {
                        $tour = $record->tour ?? $record->tourDay->tour;
                        if ($tour->isCorporate()) {
                            $link = "/admin/tour-corporate/$tour->id/edit";
                        } else {
                            $link = "/admin/tour-tps/$tour->id/edit";
                        }
                        return "<a href='{$link}' target='_blank'>$tour->group_number</a>";
                    })
                    ->color('info')
                    ->html(),

                Tables\Columns\TextColumn::make('company')
                    ->getStateUsing(function (TourDayExpense $record) {
                        $tour = $record->tour ?? $record->tourDay->tour;
                        return $tour->company->name;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('expense_date')
                    ->getStateUsing(function (TourDayExpense $record) {
                        $date = $record->tourDay->date ?? $record->date;
                        return $date->format('d/m/Y');
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('expense_type')
                    ->getStateUsing(function (TourDayExpense $record) {
                        return $record->type->getLabel();
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('expense_name')
                    ->label('Expense Name')
                    ->getStateUsing(function (TourDayExpense $record) {
                        return match ($record->type) {
                            ExpenseType::Hotel => $record->hotel?->name,
                            ExpenseType::Museum => TourService::getMuseumsByIds([1, 2])->values()->join(', '),
                            ExpenseType::Lunch, ExpenseType::Dinner => $record->restaurant?->name,
                            ExpenseType::Train => $record->train?->name,
                            ExpenseType::Show => $record->show?->name,
                            default => '',
                        };
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('tour_pax')
                    ->label('Pax')
                    ->getStateUsing(function (TourDayExpense $record) {
                        $tour = $record->tour ?? $record->tourDay->tour;
                        return $tour->getTotalPax();
                    }),

                Tables\Columns\TextColumn::make('route')
                    ->label('Route')
                    ->getStateUsing(function (TourDayExpense $record) {
                        $fromCity = $record->tourDay?->city?->name ?? $record->city?->name;

                        return match ($record->type) {
                            ExpenseType::Transport => $record->transport_route,
                            ExpenseType::Plane => $record->plane_route,
                            ExpenseType::Train => "$fromCity - {$record->toCity?->name}",
                            default => '',
                        };
                    }),

                Tables\Columns\TextColumn::make('price'),
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
