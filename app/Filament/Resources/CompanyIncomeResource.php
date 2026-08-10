<?php

namespace App\Filament\Resources;

use App\Enums\CompanyType;
use App\Enums\CurrencyEnum;
use App\Enums\PaymentStatus;
use App\Enums\TourType;
use App\Filament\Resources\CompanyIncomeResource\Pages;
use App\Filament\Resources\CompanyIncomeResource\RelationManagers;
use App\Enums\ExpenseType;
use App\Models\Company;
use App\Models\Tour;
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

class CompanyIncomeResource extends Resource
{

    // Sidebar label — Filament otherwise falls back to the auto-derived
    // English plural model name (e.g. "Hotels"), which never changes with
    // the panel's locale. See AppServiceProvider for the equivalent
    // ->translateLabel() hook covering field/column labels; this can't be
    // done the same way since getNavigationLabel() is called statically.
    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }

    // See the comment on getNavigationLabel() above / AdminPanelProvider's
    // navigationGroups() — Filament matches resources to their registered
    // group by comparing this value against the group's getLabel(), so both
    // sides need translating the same way or the match silently fails.
    public static function getNavigationGroup(): ?string
    {
        return ($group = parent::getNavigationGroup()) ? __($group) : null;
    }

    // Breadcrumb text ("X > List" above the page heading) — a third, separate
    // label pipeline from getNavigationLabel()/getNavigationGroup() above
    // (falls back to getTitleCasePluralModelLabel(), not either of those).
    public static function getBreadcrumb(): string
    {
        return __(parent::getBreadcrumb());
    }

    // Plural model label — feeds table empty states ("Не найдено tours") and
    // some page headings. Singular getModelLabel() is deliberately NOT
    // overridden; see the class-level comment above the other nav overrides.
    public static function getPluralModelLabel(): string
    {
        return __(parent::getPluralModelLabel());
    }
    protected static ?string $model = Tour::class;
    protected static ?string $label = 'Company Incomes';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
            ->schema([

            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_cancelled', false);
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
                        Forms\Components\Grid::make(['sm' => 2, 'md' => 3, 'xl' => 5])->schema([
                            Forms\Components\Select::make('tour_type')
                                ->native(false)
                                ->label(__('Tour Type'))
                                ->options([
                                    TourType::TPS->value => TourType::TPS->getLabel(),
                                    TourType::Corporate->value => TourType::Corporate->getLabel(),
                                ]),

                            Forms\Components\Select::make('companies')
                                ->native(false)
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->label(__('Company'))
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
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->columns([
                Tables\Columns\TextColumn::make('group_number')
                    ->label(__('Group number'))
                    ->getStateUsing(function (Tour $record) {
                        if ($record->isCorporate()) {
                            $link = "/admin/tour-corporate/$record->id/edit";
                        } else {
                            $link = "/admin/tour-tps/$record->id/edit";
                        }
                        return "<a href='{$link}' target='_blank'>$record->group_number</a>";
                    })
                    ->color('info')
                    ->html(),

                Tables\Columns\TextColumn::make('company')
                    ->getStateUsing(function (Tour $record) {
                        return $record->company->name;
                    })
                    ->searchable(query: fn($query, $search) => $query->whereHas(
                        'company', fn($q) => $q->whereRaw('LOWER(name::text) LIKE ?', ['%' . mb_strtolower($search) . '%'])
                    )),

                Tables\Columns\TextColumn::make('inn')
                    ->label(__('Company Inn'))
                    ->getStateUsing(function (Tour $record) {
                        return $record->company->inn;
                    })
                    ->searchable(query: fn($query, $search) => $query->whereHas(
                        'company', fn($q) => $q->whereRaw('LOWER(inn::text) LIKE ?', ['%' . mb_strtolower($search) . '%'])
                    )),

                Tables\Columns\TextColumn::make('tour_pax')
                    ->label(__('Pax'))
                    ->getStateUsing(function (Tour $record) {
                        return $record->getTotalPax();
                    }),

                Tables\Columns\TextColumn::make('sell_price')
                    ->label(__('Sell price'))
                    ->getStateUsing(function (Tour $record) {
                        if ($record->isCorporate()) {
                            // Transport: use Transfer.sell_price_result (route price charged to client)
                            $transportExpenseIds = TourDayExpense::whereIn(
                                'tour_group_id',
                                $record->groups()->pluck('id')
                            )
                                ->where('type', ExpenseType::Transport->value)
                                ->pluck('id');

                            $transportSellTotal = Transfer::whereIn('tour_day_expense_id', $transportExpenseIds)
                                ->get()
                                ->sum(fn($t) => $t->sell_price_result ?? $t->sell_price ?? 0);

                            // Non-transport: use TourDayExpense.price_result as before
                            $otherTotal = TourDayExpense::whereIn(
                                'tour_group_id',
                                $record->groups()->pluck('id')
                            )
                                ->where('type', '!=', ExpenseType::Transport->value)
                                ->sum('price_result');

                            $price = $transportSellTotal + $otherTotal;
                        } else {
                            $price = $record->total_price;
                        }
                        return TourService::formatMoney($price) . ' ' . CurrencyEnum::UZS->getSymbol();
                    }),

                Tables\Columns\SelectColumn::make('payment_status')
                    ->options(PaymentStatus::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
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
