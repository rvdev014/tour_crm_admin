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
use App\Enums\CurrencyEnum;
use App\Enums\PaymentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Transfer;
use App\Services\TourService;
use App\Models\TourDayExpense;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CompanyExpenseResource\Pages;
use App\Filament\Resources\CompanyExpenseResource\RelationManagers;

class CompanyExpenseResource extends Resource
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
    protected static ?string $model = TourDayExpense::class;
    protected static ?string $label = 'Company Expenses';
    
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
        return parent::getEloquentQuery()->where(function (Builder $query) {
            $query->where(function ($q) {
                // Path 1: Through tourGroup
                $q->whereHas('tourGroup.tour', fn ($tour) => $tour->where('is_cancelled', false))
                    // Path 2: Through tourDay
                    ->orWhereHas('tourDay.tour', fn ($tour) => $tour->where('is_cancelled', false));
            });
        });
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->filters([
                Tables\Filters\Filter::make('filters')
                    ->columnSpanFull()
                    ->form([
                        Forms\Components\Grid::make(['sm' => 2, 'md' => 3, 'xl' => 6])->schema([
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
                            $query = $query->where('payment_status', $paymentStatus);
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
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->columns([
                Tables\Columns\TextColumn::make('group_number')
                    ->label(__('Group number'))
                    ->getStateUsing(function(TourDayExpense $record) {
                        $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                        if ($tour->isCorporate()) {
                            $link = "/admin/tour-corporate/$tour->id/edit";
                        } else {
                            $link = "/admin/tour-tps/$tour->id/edit";
                        }
                        return "<a href='{$link}' target='_blank'>$tour->group_number</a>";
                    })
                    ->color('info')
                    ->html()
                    ->searchable(query: function(Builder $query, string $search): Builder {
                        return $query->where(function($q) use ($search) {
                            $q->whereHas('tourGroup.tour', function($tourQuery) use ($search) {
                                $tourQuery->where('group_number', 'ilike', "%{$search}%");
                            })
                                ->orWhereHas('tourDay.tour', function($tourQuery) use ($search) {
                                    $tourQuery->where('group_number', 'ilike', "%{$search}%");
                                });
                        });
                    }),
                
                Tables\Columns\SelectColumn::make('invoice_status')
                    ->options(InvoiceStatus::class),
                
                Tables\Columns\TextColumn::make('company')
                    ->getStateUsing(function(TourDayExpense $record) {
                        $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                        return $tour->company->name;
                    })
                    ->searchable(query: function(Builder $query, string $search): Builder {
                        return $query->where(function($q) use ($search) {
                            $q->whereHas('tourGroup.tour.company', function($companyQuery) use ($search) {
                                $companyQuery->where('name', 'ilike', "%{$search}%");
                            })
                                ->orWhereHas('tourDay.tour.company', function($companyQuery) use ($search) {
                                    $companyQuery->where('name', 'ilike', "%{$search}%");
                                });
                        });
                    }),
                
                Tables\Columns\TextColumn::make('inn')
                    ->label(__('Company Inn'))
                    ->getStateUsing(function(TourDayExpense $record) {
                        $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                        return $tour->company->inn;
                    })
                    ->searchable(query: function(Builder $query, string $search): Builder {
                        return $query->where(function($q) use ($search) {
                            $q->whereHas('tourGroup.tour.company', function($companyQuery) use ($search) {
                                $companyQuery->where('inn', 'ilike', "%{$search}%");
                            })
                                ->orWhereHas('tourDay.tour.company', function($companyQuery) use ($search) {
                                    $companyQuery->where('inn', 'ilike', "%{$search}%");
                                });
                        });
                    }),
                
                Tables\Columns\TextColumn::make('start_date')
                    ->getStateUsing(function(TourDayExpense $record) {
                        $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                        return $tour->start_date?->format('d.m.Y H:i');
                    }),
                
                Tables\Columns\TextColumn::make('expense_date')
                    ->getStateUsing(function(TourDayExpense $record) {
                        $date = $record->tourDay->date ?? $record->date;
                        return $date?->format('d.m.Y');
                    }),
                
                Tables\Columns\TextColumn::make('passengers')
                    ->label(__('Passengers FIO'))
                    ->getStateUsing(function(TourDayExpense $record) {
                        $passengers = [];
                        if ($record->tourGroup) {
                            foreach ($record->tourGroup->passengers as $passenger) {
                                $passengers[] = $passenger->name;
                            }
                        }
                        return $record->tourGroup?->passengers?->first()?->name ?? '-';
                        //                        return collect($passengers)->join(', ');
                    })
                //                    ->width('300px')
                //                    ->wrap()
                ,
                
                Tables\Columns\TextColumn::make('expense_type')
                    ->getStateUsing(function(TourDayExpense $record) {
                        return $record->type->getLabel();
                    }),
                
                Tables\Columns\TextColumn::make('expense_name')
                    ->label(__('Expense Name'))
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
                    }),
                
                Tables\Columns\TextColumn::make('tour_pax')
                    ->label(__('Pax'))
                    ->getStateUsing(function(TourDayExpense $record) {
                        $tour = $record->tourGroup?->tour ?? $record->tourDay->tour;
                        if ($record->tourGroup) {
                            return $record->tourGroup->passengers()->count();
                        }
                        return $tour->getTotalPax();
                    }),
                
                Tables\Columns\TextColumn::make('route')
                    ->label(__('Route'))
                    ->getStateUsing(function(TourDayExpense $record) {
                        $fromCity = $record->tourDay?->city?->name ?? $record->city?->name;
                        
                        return match ($record->type) {
                            ExpenseType::Transport => $record->transport_route,
                            ExpenseType::Flight    => $record->plane_route,
                            ExpenseType::Train     => "$fromCity - {$record->toCity?->name}",
                            default                => '',
                        };
                    }),
                
                Tables\Columns\TextColumn::make('buy_price')
                    ->label(__('Buy price'))
                    ->getStateUsing(function (TourDayExpense $record) {
                        if ($record->type === ExpenseType::Transport) {
                            $transfer = Transfer::where('tour_day_expense_id', $record->id)->first();
                            $price = $transfer?->buy_price_result ?? $transfer?->buy_price;
                            return TourService::formatMoney($price) . ' ' . CurrencyEnum::UZS->getSymbol();
                        }
                        return TourService::formatMoney($record->price_result) . ' ' . CurrencyEnum::UZS->getSymbol();
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
