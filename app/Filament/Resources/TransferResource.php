<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Enums\DriverTransferStatus;
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
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

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

                Forms\Components\Section::make(__('Trip details'))
                    ->icon('heroicon-o-map')
                    ->description(__('Destination city, the company that requested the transfer, and the contracted driver supplier.'))
                    ->schema([
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
                    ]),

                Forms\Components\Section::make(__('Driver'))
                    ->icon('heroicon-o-identification')
                    ->description(__('Contact details for the driver actually assigned to this transfer.'))
                    ->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('driver_name')
                        ->label(__('Driver name'))
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('driver_phone')
                        ->label(__('Driver phone number'))
                        ->tel()
                        ->columnSpan(2),
                ]),
                // What the driver reports from the cabinet. Read-only here: only the driver (or the
                // "Set driver status" header action, which is logged) changes it.
                Forms\Components\Placeholder::make('driver_status_info')
                    ->label(__('Driver status'))
                    ->visibleOn('edit')
                    ->content(function (?Transfer $record) {
                        if (empty($record?->driver_ids)) {
                            return '—';
                        }

                        $changedAt = $record->driver_status_updated_at?->diffForHumans();

                        return $record->effectiveDriverStatus()->getLabel().($changedAt ? " · {$changedAt}" : '');
                    }),
                    ]),

                Forms\Components\Section::make(__('Status & class'))
                    ->icon('heroicon-o-truck')
                    ->description(__('Passenger count, booking status, and transport class.'))
                    ->schema([
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
                        ->disabled(fn($record) => !empty($record?->tour_day_expense_id)),
                ]),
                    ]),

                Forms\Components\Section::make(__('Schedule & destination'))
                    ->icon('heroicon-o-calendar-days')
                    ->description(__('Pickup date and time, plus the free-text destination and vehicle details.'))
                    ->schema([
                Forms\Components\Grid::make(5)->schema([

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

                    // The client's phone, for the driver (call / WhatsApp / Telegram in the driver cabinet).
                    // Clients are mostly foreign tourists, so — unlike the driver form — every country is allowed.
                    // The picker's own validation is client-side only, hence the server-side rule: what gets
                    // stored must be a real international number, or a wa.me / t.me link built from it would
                    // point at the wrong person.
                    PhoneInput::make('client_phone')
                        ->label(__('Client phone'))
                        ->defaultCountry('UZ')
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? preg_replace('/[\s\-().]/', '', $state) : null)
                        ->rules(fn (?Model $record) => [
                            function (string $attribute, mixed $value, \Closure $fail) use ($record) {
                                // Empty is fine, and so is a value nobody touched: numbers copied from the website
                                // are stored as the customer typed them, and an operator saving an old transfer
                                // must not be forced to rewrite a number they never edited.
                                if (blank($value) || $value === $record?->client_phone) {
                                    return;
                                }

                                if (! preg_match('/^\+[1-9]\d{6,14}$/', preg_replace('/[\s\-().]/', '', (string) $value))) {
                                    $fail(__('Enter the phone number in international format, e.g. +44 7911 123456.'));
                                }
                            },
                        ]),
                ]),
                    ]),

                Forms\Components\Section::make(__('Pricing'))
                    ->icon('heroicon-o-banknotes')
                    ->description(__('Sell and buy price, with a one-click currency toggle.'))
                    ->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('sell_price')
                        ->label(fn($get) => __('Sell price') . ' (' . ($get('sell_price_currency') ?? 'UZS') . ')')
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
                        ->label(fn($get) => __('Buy price') . ' (' . ($get('buy_price_currency') ?? 'UZS') . ')')
                        ->suffixAction(
                            Components\Actions\Action::make('toggle-currency')
                                ->icon('heroicon-o-banknotes')
                                ->iconSize('md')
                                ->action(function($get, $set) {
                                    $set('buy_price_currency', $get('buy_price_currency') != 'USD' ? 'USD' : 'UZS');
                                })
                        )
                        ->numeric(),
                    Forms\Components\Textarea::make('comment')
                        ->label(__('Comment'))
                        ->columnSpanFull(),
                ]),
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
            // The order is NOT set here: an orderBy added in modifyQueryUsing runs before Filament applies the
            // column the user clicked, so it always won and the sort arrows did nothing. defaultSort() is
            // skipped by Filament as soon as a column is chosen, which is exactly what we want.
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'toCity', 'company', 'createdBy',
                // read by the "Number" cell to show the tour number under the transfer number
                'tourDayExpense.tourGroup.tour', 'tourDayExpense.tour', 'tourDayExpense.tourDay.tour',
            ]))
            // Today and later first (soonest at the top), then the past (most recent first) — same as before.
            ->defaultSort(function (Builder $query) use ($table) {
                // Grouped by day the days are already in order, so inside a day it is simply by time.
                if ($table->getLivewire()->getTableGrouping()?->getId() === 'date_time') {
                    return $query->orderBy('date_time');
                }

                $now = Carbon::today()->toDateTimeString();

                return $query->orderByRaw(
                    'CASE WHEN date_time >= ?::timestamp THEN 0 ELSE 1 END, ABS(EXTRACT(EPOCH FROM (date_time - ?::timestamp))) ASC',
                    [$now, $now],
                );
            })
            ->persistSortInSession()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->groups([
                static::dayGroup($table),
                Tables\Grouping\Group::make('company.name')->label(__('Company')),
                Tables\Grouping\Group::make('status')->label(__('Status')),
            ])
            ->defaultGroup('date_time')
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
                            Components\Select::make('driver_statuses')
                                ->label(__('Driver status'))
                                ->native(false)
                                ->multiple()
                                ->preload()
                                ->options(DriverTransferStatus::class),
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
                            $query = static::whereAnyDriver($query, $data['driver_ids']);
                        }
                        if ($data['driver_statuses'] ?? null) {
                            $query = static::whereDriverStatusIn($query, $data['driver_statuses']);
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
                            $query = static::whereAnyDriver($query, $data['driver_ids']);
                            $drivers = Driver::query()->whereIn('id', $data['driver_ids'])->get();
                            $driverNames = $drivers->map(fn($driver) => $driver->name)->join(', ');
                            $indicators['driver_ids'] = $driverNames . " ({$query->count()})";
                        }
                        if ($data['driver_statuses'] ?? null) {
                            $query = static::whereDriverStatusIn($query, $data['driver_statuses']);
                            $labels = collect($data['driver_statuses'])
                                ->map(fn ($status) => DriverTransferStatus::from($status)->getLabel())
                                ->join(', ');
                            $indicators['driver_statuses'] = __('Driver status') . ': ' . $labels . " ({$query->count()})";
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
                // A cell shows its second line only when its own value is not empty, so a missing top value is shown
                // as a dash (->default('—')): otherwise a transfer with a tour but no number would lose the tour.

                // Number, with the tour it belongs to underneath (was two columns).
                Tables\Columns\TextColumn::make('number')
                    ->label(__('Number'))
                    ->default('—')
                    ->description(fn (Transfer $record) => static::tourNumber($record))
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search) => static::searchTransfer(
                        $query, $search, ['number', 'group_number'],
                        ['tourDayExpense.tourGroup.tour' => 'group_number', 'tourDayExpense.tour' => 'group_number', 'tourDayExpense.tourDay.tour' => 'group_number'],
                    )),

                // Company, with who asked for the transfer underneath.
                Tables\Columns\TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->default('—')
                    ->description(fn (Transfer $record) => filled($record->requested_by) ? $record->requested_by : null)
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search) => static::searchTransfer(
                        $query, $search, ['requested_by'], ['company' => 'name'],
                    )),

                // Date and time on two labelled lines. The one column that renders HTML; every value in it is escaped,
                // and the Excel export replaces it (see ListTransfers), so no markup ever reaches a spreadsheet.
                Tables\Columns\TextColumn::make('date_time')
                    ->label(__('Date & Time'))
                    ->formatStateUsing(fn ($state) => blank($state) ? '—' : static::labelled(__('Date'), $state->format('d.m.Y'))
                        .static::labelled(__('Time'), $state->format('H:i')))
                    ->html()
                    ->sortable(),

                // Destination, with the city underneath (was "Location").
                Tables\Columns\TextColumn::make('route')
                    ->label(__('Destination'))
                    ->default('—')
                    ->wrap()
                    ->lineClamp(2)
                    ->description(fn (Transfer $record) => $record->toCity?->name)
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search) => static::searchTransfer(
                        $query, $search, ['route'], ['toCity' => 'name'],
                    )),

                // Passenger count with the pickup sign underneath — the sign is what the driver holds up.
                Tables\Columns\TextColumn::make('pax')
                    ->label(__('Pax'))
                    ->icon('heroicon-m-user')
                    ->default('—')
                    ->description(fn (Transfer $record) => filled($record->nameplate) ? $record->nameplate : null)
                    ->wrap()
                    ->searchable(['nameplate'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('driver_name')
                    ->label(__('Driver'))
                    ->default('—')
                    ->description(fn (Transfer $record) => filled($record->driver_phone) ? $record->driver_phone : null)
                    ->sortable()
                    ->searchable(['driver_name', 'driver_phone']),

                // What the driver last reported from the cabinet. NULL in the DB reads as "Assigned"; a
                // transfer with no driver at all shows nothing rather than a misleading "Assigned".
                // Not sortable: a plain varchar sort would be alphabetical, not lifecycle order.
                Tables\Columns\TextColumn::make('driver_status')
                    ->label(__('Driver status'))
                    ->badge()
                    ->getStateUsing(fn (Transfer $record) => empty($record->driver_ids) ? null : $record->effectiveDriverStatus())
                    ->description(fn (Transfer $record) => empty($record->driver_ids) ? null : $record->driver_status_updated_at?->diffForHumans())
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                // Sell price, with the purchase price underneath (was two columns; sorting is by sell price).
                Tables\Columns\TextColumn::make('sell_price')
                    ->label(__('Sell price'))
                    ->getStateUsing(fn (Transfer $record) => filled($record->sell_price)
                        ? \Filament\Support\format_money($record->sell_price, Tables\Table::$defaultCurrency)
                        : '—')
                    ->description(fn (Transfer $record) => filled($record->buy_price)
                        ? __('Buy price').': '.\Filament\Support\format_money($record->buy_price, Tables\Table::$defaultCurrency)
                        : null)
                    ->sortable(),

                // Rarely needed, so off by default; the "Columns" menu turns them on.
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

    /**
     * Transfers assigned to ANY of the given drivers.
     *
     * driver_ids is a varchar column holding a JSON array of ids stored as STRINGS (["2","6"]), so the
     * ids are cast to string. The previous code passed the whole array to a single whereJsonContains,
     * which is `@> '["2","6"]'` — "assigned to ALL of them" — so picking two drivers only ever matched
     * transfers that both were on.
     */
    public static function whereAnyDriver(Builder $query, array $driverIds): Builder
    {
        return $query->where(function (Builder $q) use ($driverIds) {
            foreach ($driverIds as $id) {
                $q->orWhereJsonContains('driver_ids', (string) $id);
            }
        });
    }

    /**
     * Filter by the status the driver reported. A NULL column means the driver has not acted yet, which
     * reads as "Assigned" — so filtering for Assigned must include the NULL rows, or it would match
     * almost nothing. Transfers with no driver at all are excluded.
     */
    public static function whereDriverStatusIn(Builder $query, array $statuses): Builder
    {
        return $query->whereNotNull('driver_ids')
            ->where('driver_ids', '!=', '[]')
            ->where(function (Builder $q) use ($statuses) {
                $q->whereIn('driver_status', $statuses);

                if (in_array(DriverTransferStatus::Assigned->value, $statuses, true)) {
                    $q->orWhereNull('driver_status');
                }
            });
    }

    /** One "label value" line of the date cell. Escaped: the cell is rendered as HTML. */
    protected static function labelled(string $label, string $value): string
    {
        return '<span class="ep-kv"><span class="ep-kv__k">'.e($label).'</span>'.e($value).'</span>';
    }

    /**
     * A phone number for a spreadsheet cell. A bare "+998901112233" is read as a NUMBER — the plus disappears and a
     * long one can lose digits — so Uzbek numbers are spaced, and any other all-digit number gets a space after the
     * first three characters. Anything already containing separators is left alone.
     */
    public static function phoneAsText(?string $phone): ?string
    {
        if (blank($phone) || ! preg_match('/^\+?\d{6,}$/', $phone)) {
            return $phone;
        }

        if (preg_match('/^(\+?998)(\d{2})(\d{3})(\d{2})(\d{2})$/', $phone, $m)) {
            return "{$m[1]} {$m[2]} {$m[3]} {$m[4]} {$m[5]}";
        }

        return preg_replace('/^(\+?\d{3})(\d+)$/', '$1 $2', $phone);
    }

    /** The number of the tour a transfer belongs to (null for a standalone transfer). Also used by the Excel export. */
    public static function tourGroupNumber(Transfer $record): ?string
    {
        $tour = $record->tourDayExpense?->tourGroup?->tour
            ?? $record->tourDayExpense?->tour
            ?? $record->tourDayExpense?->tourDay?->tour;
        $number = $tour?->group_number ?? $record->group_number;

        return filled($number) ? (string) $number : null;
    }

    /** The line under the transfer number. */
    protected static function tourNumber(Transfer $record): ?string
    {
        $number = static::tourGroupNumber($record);

        return $number === null ? null : __('Tour').' '.$number;
    }

    /**
     * Case-insensitive "contains" search over some columns and some relations' columns, OR-ed together.
     * Filament hands the closure its own nested where-group, so the ORs cannot leak into the rest of the query.
     * LIKE wildcards typed by the user are escaped.
     *
     * @param  array<int, string>  $columns
     * @param  array<string, string>  $relations  relation (dot notation allowed) => column
     */
    protected static function searchTransfer(Builder $query, string $search, array $columns, array $relations = []): Builder
    {
        $like = '%'.addcslashes($search, '%_\\').'%';

        foreach ($columns as $column) {
            $query->orWhere($column, 'ilike', $like);
        }
        foreach ($relations as $relation => $column) {
            $query->orWhereHas($relation, fn (Builder $q) => $q->where($column, 'ilike', $like));
        }

        return $query;
    }

    /**
     * Rows grouped under a heading per calendar day.
     *
     * Not ->date(): that would print the heading in Filament's fixed "Sep 14, 2026" format. Days run today ->
     * future -> past (nearest first), the same idea as the flat list's order but by whole days, because ordering
     * by distance in hours would interleave two days. The direction toggle of the group menu is honoured.
     */
    protected static function dayGroup(Table $table): Tables\Grouping\Group
    {
        return Tables\Grouping\Group::make('date_time')
            ->label(__('Date'))
            ->collapsible()
            ->getKeyFromRecordUsing(fn (Transfer $record) => $record->date_time?->toDateString())
            ->getTitleFromRecordUsing(fn (Transfer $record) => $record->date_time?->translatedFormat('d.m.Y, l') ?? '—')
            ->getDescriptionFromRecordUsing(fn (Transfer $record) => static::dayCount($table, $record))
            ->groupQueryUsing(fn ($query) => $query->groupByRaw('date(date_time)'))
            ->scopeQueryByKeyUsing(fn ($query, string $key) => $query->whereDate('date_time', $key))
            ->orderQueryUsing(function (Builder $query, string $direction) {
                if ($direction === 'desc') {
                    return $query->orderByRaw('date_time::date DESC');
                }

                $today = Carbon::today()->toDateString();

                return $query
                    ->orderByRaw('CASE WHEN date_time::date >= ?::date THEN 0 ELSE 1 END', [$today])
                    ->orderByRaw('ABS(date_time::date - ?::date)', [$today]);
            });
    }

    /**
     * "Transfers: 6" for a day heading. Counted over the whole filtered list, not the current page, so a day that
     * continues on the next page still shows its real total. One grouped query per render, not one per heading.
     */
    protected static function dayCount(Table $table, Transfer $record): ?string
    {
        static $cache;
        $cache ??= new \WeakMap;

        $livewire = $table->getLivewire();

        if (! isset($cache[$livewire])) {
            $cache[$livewire] = $livewire->getFilteredTableQuery()
                ->toBase()
                ->cloneWithout(['columns', 'orders'])
                ->cloneWithoutBindings(['select', 'order'])
                ->selectRaw('date(date_time) as day, count(*) as total')
                ->groupByRaw('date(date_time)')
                ->pluck('total', 'day')
                ->all();
        }

        $total = $cache[$livewire][$record->date_time?->toDateString()] ?? null;

        return $total === null ? null : __('Transfers').': '.$total;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DriverStatusLogsRelationManager::class,
            RelationManagers\DriverExpensesRelationManager::class,
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
