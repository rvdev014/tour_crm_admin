<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Enums\ExpenseStatus;
use App\Filament\Resources\TransferResource\Pages;
use App\Filament\Resources\TransferResource\RelationManagers;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Transfer;
use App\Services\TourService;
use Filament\Forms;
use Filament\Forms\Components;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TransferResource extends Resource
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
    protected static ?string $model = Transfer::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'id';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'number',
            'group_number',
            'company.name',
            'route',
            'mark',
            'nameplate',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return 'Transfer #' . $record->number . ", $record->pax pax";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Company'    => $record->company?->name ?? 'N/A',
            'Route'      => $record->route ?? 'N/A',
            'Date & Time'=> $record->date_time?->format('d.m.Y H:i') ?? 'N/A',
        ];
    }

    public static function canEdit(Model $record): bool
    {
        if ($record->status == ExpenseStatus::Done && !auth()->user()->isAdmin()) {
            return false;
        }
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();
        if (!$user->isAdmin()) {
            return parent::getEloquentQuery()->where('created_by', $user->id);
        }

        return parent::getEloquentQuery();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('sell_price_currency'),
                Hidden::make('buy_price_currency'),
                Forms\Components\Grid::make(4)->schema([

                    Forms\Components\Select::make('to_city_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->label(__('City'))
                        ->relationship('toCity', 'name')
                        ->options(TourService::getCities())
                        ->preload()
                        ->reactive(),

                    Forms\Components\Select::make('company_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->label(__('Company'))
                        ->relationship('company', 'name'),

                    Forms\Components\TextInput::make('requested_by'),

                    Forms\Components\Select::make('driver_ids')
                        ->label(__('Driver supplier'))
                        ->options(TourService::getDrivers())
                        ->native(false)
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ]),

                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('driver_name')
                        ->label(__('Driver name'))
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('driver_phone')
                        ->label(__('Driver phone number'))
                        ->tel()
                        ->columnSpan(2),
                ]),

                Forms\Components\Grid::make(4)->schema([

                    Forms\Components\TextInput::make('pax')
                        ->label(__('Pax'))
                        ->numeric()
                        ->readOnly(fn($record) => !empty($record?->tour_day_expense_id)),

                    Forms\Components\Select::make('status')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(function($get, $record) {
                            $options = [
                                ExpenseStatus::New->value => ExpenseStatus::New->getLabel(),
                                ExpenseStatus::Confirmed->value => ExpenseStatus::Confirmed->getLabel(),
                                ExpenseStatus::Rejected->value => ExpenseStatus::Rejected->getLabel(),
                            ];
                            
                            $dateTime = $get('date_time') ? Carbon::parse($get('date_time')) : null;
                            if ($dateTime?->isPast()) {
                                $options[ExpenseStatus::Done->value] = ExpenseStatus::Done->getLabel();
                            }
                            
                            return $options;
                        })
                        ->label(__('Status')),

                    Forms\Components\Select::make('transport_class_id')
                        ->label(__('Transport class'))
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(fn() => TourService::getTransportClasses())
                        ->disabled(fn($record) => !empty($record?->tour_day_expense_id))
                        ->reactive()
                        ->afterStateUpdated(function ($state, $set) {
                            $set('route_id', null);
                            $set('sell_price', null);
                        }),

                    Forms\Components\Select::make('route_id')
                        ->label(__('Route'))
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(fn($get) => $get('transport_class_id')
                            ? TourService::getRoutesForTransportClass((int)$get('transport_class_id'))
                            : []
                        )
                        ->disabled(fn($record) => !empty($record?->tour_day_expense_id))
                        ->reactive()
                        ->afterStateUpdated(function ($state, $get, $set) {
                            if ($state && $get('transport_class_id')) {
                                $price = TourService::getRoutePriceForTransportClass(
                                    (int)$state,
                                    (int)$get('transport_class_id')
                                );
                                if ($price !== null) {
                                    $set('sell_price', $price);
                                    $set('sell_price_currency', 'USD');
                                }
                            }
                        }),
                ]),

                Forms\Components\Grid::make(4)->schema([

                    Forms\Components\DateTimePicker::make('date_time')
                        ->displayFormat('d.m.Y H:i')
//                        ->native(false)
                        ->seconds(false),

                    Forms\Components\TextInput::make('route')
                        ->label(__('Destination')),

                    Forms\Components\TextInput::make('mark')
                        ->label(__('Marka')),
                    Forms\Components\TextInput::make('nameplate')
                        ->label(__('Табличка')),
                ]),

                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('sell_price')
                        ->label(fn($get) => 'Sell price (' . ($get('sell_price_currency') ?? 'UZS') . ')')
                        ->suffixAction(
                            Components\Actions\Action::make('toggle-currency')
                                ->icon('heroicon-o-banknotes')
                                ->iconSize('md')
                                ->action(function($get, $set) {
                                    $set('sell_price_currency', $get('sell_price_currency') != 'USD' ? 'USD' : 'UZS');
                                })
                        )
                        ->numeric(),
                    Forms\Components\TextInput::make('buy_price')
                        ->label(fn($get) => 'Buy price (' . ($get('buy_price_currency') ?? 'UZS') . ')')
                        ->suffixAction(
                            Components\Actions\Action::make('toggle-currency')
                                ->icon('heroicon-o-banknotes')
                                ->iconSize('md')
                                ->action(function($get, $set) {
                                    $set('buy_price_currency', $get('buy_price_currency') != 'USD' ? 'USD' : 'UZS');
                                })
                        )
                        ->numeric(),
                    Forms\Components\Textarea::make('comment'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->searchable()
            ->modifyQueryUsing(function ($query) {
                $now = Carbon::today()->toDateTimeString();

                $query
                    ->with(['toCity', 'company', 'createdBy'])
                    ->orderByRaw(
                        "
CASE
    WHEN date_time >= ?::timestamp THEN 0
    ELSE 1
END,
    ABS(EXTRACT(EPOCH FROM (date_time - ?::timestamp))) ASC
                    ",
                        [$now, $now]
                    );
            })
//            ->defaultSort('date_time', 'desc')
            ->filtersFormColumns(3)
            // Was: an `if` returning ' color-green' for Done ahead of a match() that
            // also handled Done, so that arm of the match could never run — and
            // 'color-light-orange' actually rendered flat grey (#c0c0c0), not
            // orange. Row highlight classes now come from the same semantic
            // tokens as everything else (theme.css's .ep-row-*), Confirmed
            // mapped to warning so it's a visible "needs attention" cue instead
            // of a no-op grey.
            ->recordClasses(fn ($record) => match ($record->status) {
                ExpenseStatus::Done => 'ep-row-success',
                ExpenseStatus::Rejected => 'ep-row-danger',
                ExpenseStatus::Confirmed => 'ep-row-warning',
                default => null,
            })
            ->filters([
                Tables\Filters\Filter::make('today')
                    ->columnSpanFull()
                    ->form([
                        Components\Grid::make(['sm' => 2, 'md' => 4, 'xl' => 7])->schema([
                            Components\Checkbox::make('today')
                                ->label(__('Today'))
                                ->default(false),
                            Components\Checkbox::make('tomorrow')
                                ->label(__('Tomorrow'))
                                ->default(false),
                            Components\Select::make('driver_ids')
                                ->label(__('Drivers'))
                                ->options(TourService::getDrivers())
                                ->native(false)
                                ->multiple()
                                ->searchable()
                                ->preload(),
                            Components\Select::make('companies')
                                ->native(false)
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->label(__('Company'))
                                ->relationship('company', 'name'),
                            Components\Select::make('statuses')
                                ->native(false)
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(ExpenseStatus::class),
                            Components\DatePicker::make('date_from')
                                ->displayFormat('d.m.Y')
                                ->native(false),
                            Components\DatePicker::make('date_until')
                                ->displayFormat('d.m.Y')
                                ->native(false),
                        ])
                    ])
                    ->query(function (Builder $query, $data) {
                        if ($data['today'] && $data['tomorrow']) {
                            $query = $query
                                ->whereDate('date_time', Carbon::today())
                                ->orWhereDate('date_time', Carbon::tomorrow());
                        } else {
                            if ($data['today']) {
                                $query = $query->whereDate('date_time', Carbon::today());
                            }
                            if ($data['tomorrow']) {
                                $tomorrow = Carbon::tomorrow();
                                $query = $query->whereDate('date_time', $tomorrow);
                            }
                        }
                        if ($data['statuses']) {
                            $query = $query->whereIn('status', $data['statuses']);
                        }
                        if ($data['companies']) {
                            $query = $query->whereIn('company_id', $data['companies']);
                        }
                        if ($data['driver_ids']) {
                            $query = $query->whereJsonContains('driver_ids', $data['driver_ids']);
                        }
                        if ($data['date_from']) {
                            $query = $query->whereDate('date_time', '>=', $data['date_from']);
                        }
                        if ($data['date_until']) {
                            $query = $query->whereDate('date_time', '<=', $data['date_until']);
                        }
                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $query = Transfer::query();

                        $indicators = [];
                        if ($data['today'] && $data['tomorrow']) {
                            $query = $query
                                ->whereDate('date_time', Carbon::today())
                                ->orWhereDate('date_time', Carbon::tomorrow());
                            $indicators['today'] = "Today & Tomorrow ({$query->count()})";
                        }

                        if ($data['today']) {
                            $query = $query->whereDate('date_time', Carbon::today());
                            $indicators['today'] = "Today ({$query->count()})";
                        }
                        if ($data['tomorrow']) {
                            $query = $query->whereDate('date_time', Carbon::tomorrow());
                            $indicators['tomorrow'] = "Tomorrow ({$query->count()})";
                        }
                        if ($data['statuses']) {
                            $query = $query->whereIn('status', $data['statuses']);
                            $statuses = collect($data['statuses'])->map(
                                fn($status) => ExpenseStatus::from($status)->getLabel()
                            )->join(', ');
                            $indicators['statuses'] = 'Status: ' . $statuses . " ({$query->count()})";
                        }
                        if ($data['companies']) {
                            $query = $query->whereIn('company_id', $data['companies']);
                            $companies = Company::query()->whereIn('id', $data['companies'])->get();
                            $companyNames = $companies->map(fn($company) => $company->name)->join(', ');
                            $indicators['company_id'] = $companyNames . " ({$query->count()})";
                        }
                        if ($data['driver_ids']) {
                            $query = $query->whereJsonContains('driver_ids', $data['driver_ids']);
                            $drivers = Driver::query()->whereIn('id', $data['driver_ids'])->get();
                            $driverNames = $drivers->map(fn($driver) => $driver->name)->join(', ');
                            $indicators['driver_ids'] = $driverNames . " ({$query->count()})";
                        }
                        if ($data['date_from']) {
                            $indicators['date_from'] = 'Order from ' . Carbon::parse(
                                    $data['date_from']
                                )->toFormattedDateString();
                        }
                        if ($data['date_until']) {
                            $indicators['date_until'] = 'Order until ' . Carbon::parse(
                                    $data['date_until']
                                )->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label(__('Number'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('tour_id')
                    ->label(__('Tour'))
                    ->getStateUsing(function (Transfer $record) {
                        $tour = $record->tourDayExpense?->tourGroup?->tour
                            ?? $record->tourDayExpense?->tour
                            ?? $record->tourDayExpense?->tourDay?->tour
                            ?? null;
                        return $tour?->group_number ?? $record->group_number ?? '-';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query
                            ->where('group_number', 'like', "%{$search}%")
                            ->orWhereHas('tourDayExpense.tourGroup.tour', function (Builder $query) use ($search) {
                                $query->where('group_number', 'like', "%{$search}%");
                            })
                            ->orWhereHas('tourDayExpense.tour', function (Builder $query) use ($search) {
                                $query->where('group_number', 'like', "%{$search}%");
                            })
                            ->orWhereHas('tourDayExpense.tourDay.tour', function (Builder $query) use ($search) {
                                $query->where('group_number', 'like', "%{$search}%");
                            });
                    }),

                Tables\Columns\TextColumn::make('company.name')
                    ->label(__('Company')),

                Tables\Columns\TextColumn::make('date_time')
                    ->label(__('Date & Time'))
                    ->dateTime()
                    ->formatStateUsing(function ($state) {
                        return <<<HTML
<div style="text-align: center">
    <p>{$state->format('d.m.Y')} {$state->format('H:i')}</p>
</div>
HTML;
                    })
                    ->html()
                    ->sortable(),

                Tables\Columns\TextColumn::make('route')
                    ->label(__('Destination'))
                    ->limit(50),

                Tables\Columns\TextColumn::make('pax')
                    ->formatStateUsing(function ($record, $state) {
                        return $state . ' pax';
                    })
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('toCity.name')
                    ->label(__('Location'))
                /*->formatStateUsing(function ($record, $state) {
                    return $state . ' - ' . $record->toCity?->name;
                })*/,

                Tables\Columns\TextColumn::make('driver_name')
                    ->label(__('Driver'))
                    ->formatStateUsing(function ($record) {
                        $parts = array_filter([
                            $record->driver_name,
                            $record->driver_phone,
                        ]);
                        return implode(' / ', $parts);
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('requested_by'),

                Tables\Columns\TextColumn::make('createdBy.name'),

                //                Tables\Columns\TextColumn::make('transport_comfort_level')->sortable(),

                Tables\Columns\TextColumn::make('sell_price')
                    ->money()
                    ->sortable(),

                Tables\Columns\TextColumn::make('buy_price')
                    ->money()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable(),
                //                Tables\Columns\TextColumn::make('updated_at')
                //                    ->dateTime()
                //                    ->sortable()
                //                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransfers::route('/'),
            'create' => Pages\CreateTransfer::route('/create'),
            'edit' => Pages\EditTransfer::route('/{record}/edit'),
        ];
    }
}
