<?php

namespace App\Filament\Resources;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\City;
use App\Models\Tour;
use App\Models\User;
use Filament\Tables;
use App\Enums\TourType;
use App\Models\Company;
use App\Models\Country;
use App\Enums\GuideType;
use Filament\Forms\Form;
use App\Enums\CompanyType;
use App\Enums\ExpenseType;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use App\Enums\CurrencyEnum;
use App\Enums\ExpenseStatus;
use App\Enums\TransportType;
use Filament\Tables\Columns;
use App\Services\TourService;
use Filament\Forms\Components;
use Illuminate\Support\Carbon;
use App\Services\ExpenseService;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Concerns\InteractsWithForms;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use App\Filament\Resources\TourTpsTestResource\RelationManagers;
use App\Filament\Resources\TourTpsTestResource\Pages;
use App\Filament\Resources\TourTpsResource\Actions\StatusAction;

class TourTpsTestResource extends Resource
{
    use InteractsWithForms;

    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'TPS';
    protected static ?string $slug = 'tour-tps-test';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'group_number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('days.expenses')
            ->where('type', TourType::TPS->value);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Components\Fieldset::make('Tour details')->schema([
                Hidden::make('price_currency'),
                Hidden::make('guide_price_currency'),
                Hidden::make('transport_price_currency'),
                Components\Grid::make(4)->schema([
                    Components\TextInput::make('group_number')
                        ->formatStateUsing(function($record) {
                            if (!empty($record)) {
                                return $record->group_number;
                            }
                            return TourService::getGroupNumber(TourType::TPS);
                        })
                        ->readOnly(),
                    Components\Select::make('company_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(TourService::getCompanies([CompanyType::TPS, CompanyType::Private]))
                        ->reactive()
                        ->required(),
                    Components\Select::make('country_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->relationship('country', 'name')
                        ->afterStateUpdated(fn($get, $set) => $set('city_id', null))
                        ->reactive()
                        ->required(),
                    Components\Select::make('city_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(fn($get) => TourService::getCities($get('country_id')))
                        ->preload()
                        ->reactive(),
                ]),
                Components\Grid::make(4)->schema([
                    Components\DateTimePicker::make('start_date')
                        ->displayFormat('d.m.Y H:i')
                        ->label('Arrival time')
//                        ->native(false)
                        ->seconds(false)
                        ->minDate(fn($record) => $record ? $record->start_date : null)
                        ->afterStateUpdated(function($get, $set) {
                            $startDate = $get('start_date');
                            $firstDay = $get('days') ? Arr::first($get('days')) : null;
                            $firstDayUuid = $firstDay ? Arr::first(array_keys($get('days'))) : null;

                            if (empty($firstDay['id'])) {
                                $set("days.$firstDayUuid.date", $startDate);
                            }

                            if (Carbon::parse($get('end_date')) < Carbon::parse($startDate)) {
                                $set('end_date', null);
                            }
                        })
                        ->reactive()
                        ->required(),
                    Components\TextInput::make('arrival_number')
                        ->label('Arrival reys number'),

                    Components\DateTimePicker::make('end_date')
                        ->displayFormat('d.m.Y H:i')
                        ->label('Departure time')
//                        ->native(false)
                        ->seconds(false)
                        ->minDate(fn($get) => Carbon::parse($get('start_date'))->addDay()->format('d.m.Y H:i'))
                        ->reactive()
                        ->required(),
                    Components\TextInput::make('departure_number')
                        ->label('Departure reys number'),
                ]),
                Components\Grid::make(4)->schema([
                    Components\TextInput::make('pax')
                        ->required()
                        ->numeric(),
                    Components\TextInput::make('leader_pax')
                        ->numeric(),
                    Components\TextInput::make('price')
                        ->label(fn($get) => 'Price (' . ($get('price_currency') ?? 'UZS') . ')')
                        ->suffixAction(
                            Components\Actions\Action::make('toggle-currency')
                                ->icon('heroicon-o-banknotes')
                                ->iconSize('md')
                                ->action(function($get, $set) {
                                    $set('price_currency', $get('price_currency') != 'USD' ? 'USD' : 'UZS');
                                })
                        )
                        ->numeric(),
                    Components\TextInput::make('single_supplement_price')
                        ->numeric(),
                ]),
                Components\Grid::make(4)->schema([
                    Components\Select::make('payment_type')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(PaymentType::class)
                        ->reactive(),
                    Components\Select::make('payment_status')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(PaymentStatus::class)
                        ->reactive(),
                    Components\TextInput::make('package_name'),
                    Components\Textarea::make('comment'),
                ]),
            ]),

            Components\Fieldset::make('Guide info')->schema([
                Components\Grid::make(4)->schema([
                    Components\Select::make('guide_type')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(GuideType::class)
                        ->reactive()
                        ->required(),

                    Components\TextInput::make('guide_name')
                        ->visible(fn($get) => $get('guide_type') == GuideType::Escort->value),

                    PhoneInput::make('guide_phone')
                        ->strictMode()
                        ->onlyCountries(['UZ'])
                        ->defaultCountry('UZ')
                        ->visible(fn($get) => $get('guide_type') == GuideType::Escort->value),

                    Components\TextInput::make('guide_price')
                        ->label(fn($get) => 'Price (' . ($get('guide_price_currency') ?? 'UZS') . ')')
                        ->suffixAction(
                            Components\Actions\Action::make('toggle-currency')
                                ->icon('heroicon-o-banknotes')
                                ->iconSize('md')
                                ->action(function ($get, $set) {
                                    $set('guide_price_currency', $get('guide_price_currency') == 'USD' ? 'UZS' : 'USD');
                                })
                        )
                        ->numeric()
                        ->visible(fn($get) => $get('guide_type') == GuideType::Escort->value),
                ])
            ]),

            Components\Fieldset::make('Transport info')->schema([
                Components\Grid::make(4)->schema([
                    Components\Select::make('transport_type')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(TransportType::class),
                    Components\TextInput::make('transport_price')
                        ->label(fn($get) => 'Transfer Price (' . ($get('transport_price_currency') ?? 'UZS') . ')')
                        ->suffixAction(
                            Components\Actions\Action::make('toggle-currency')
                                ->icon('heroicon-o-banknotes')
                                ->iconSize('md')
                                ->action(function ($get, $set) {
                                    $set('transport_price_currency', $get('transport_price_currency') == 'USD' ? 'UZS' : 'USD');
                                })
                        )
                        ->numeric(),
                ])
            ]),

            Components\Fieldset::make('Rooming info')->schema([

                ...TourService::generateRoomingSchema(true),

                Section::make("Other rooming")
                    ->schema(TourService::generateRoomingSchema())
                    ->collapsible()
                    ->collapsed(),
            ])
        ]);
    }

    public static function getExpensePriceInput(string $label = 'Price'): Components\TextInput
    {
        return Components\TextInput::make('price')
            ->label(fn($get) => "$label (" . ($get('price_currency') ?? 'UZS') . ")")
            ->suffixAction(
                Components\Actions\Action::make('toggle-currency')
                    ->icon('heroicon-o-banknotes')
                    ->iconSize('md')
                    ->action(function($get, $set) {
                        $set('price_currency', $get('price_currency') != 'USD' ? 'USD' : 'UZS');
                    })
            )
            ->numeric();
    }

    public static function isLunch($expenseType): bool
    {
        return in_array($expenseType, [ExpenseType::Lunch->value, ExpenseType::Dinner->value]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function($query) {
                return $query
                    ->without('days', 'days.expenses')
                    ->with('company', 'createdBy', 'country');
            })
            ->striped()
            ->defaultSort('start_date', 'desc')
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->filters([
                Tables\Filters\Filter::make('country_id')
                    ->columnSpanFull()
                    ->form([
                        Components\Grid::make(6)->schema([
                            Components\Checkbox::make('active')
                                ->label('Active')
                                ->default(false),
                            Components\Checkbox::make('archive')
                                ->label('Archive')
                                ->default(false),
                        ]),
                        Components\Grid::make(6)->schema([
                            Components\Select::make('country_id')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(Country::all()->pluck('name', 'id')->toArray()),
                            Components\Select::make('city_id')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(fn($get) => TourService::getCities($get('country_id'))),
                            Components\Select::make('company_id')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(Company::query()->pluck('name', 'id')->toArray()),
                            Components\Select::make('created_by')
                                ->label('Admin creator')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options(User::query()->pluck('name', 'id')->toArray()),

                            Components\DatePicker::make('created_from')
                                ->displayFormat('d.m.Y')
                                ->native(false),
                            Components\DatePicker::make('created_until')
                                ->displayFormat('d.m.Y')
                                ->native(false),
                        ])
                    ])
                    ->query(function(Builder $query, $data) {
                        if ($data['active'] && $data['archive']) {
                            $query->where(function($query) use ($data) {
                                $query->where('start_date', '>=', Carbon::now())
                                    ->orWhere('start_date', '<', Carbon::now());
                            });
                        } elseif ($data['active']) {
                            $query->where('start_date', '>=', Carbon::now());
                        } elseif ($data['archive']) {
                            $query->where('start_date', '<', Carbon::now());
                        }

                        return $query
                            ->when(
                                $data['country_id'],
                                fn($query, $countryId) => $query->where('country_id', $countryId)
                            )
                            ->when($data['city_id'], fn($query, $cityId) => $query->where('city_id', $cityId))
                            ->when(
                                $data['company_id'],
                                fn($query, $companyId) => $query->where('company_id', $companyId)
                            )
                            ->when(
                                $data['created_by'],
                                fn($query, $createdBy) => $query->where('created_by', $createdBy)
                            )
                            ->when(
                                $data['created_from'],
                                fn($query, $createdFrom) => $query->whereDate('start_date', '>=', $createdFrom)
                            )
                            ->when(
                                $data['created_until'],
                                fn($query, $createdUntil) => $query->whereDate('start_date', '<=', $createdUntil)
                            );
                    })
                    ->indicateUsing(function(array $data): array {
                        $query = Tour::query()->where('type', TourType::TPS->value);

                        $indicators = [];
                        if ($data['active'] && $data['archive']) {
                            $query = $query
                                ->where('start_date', '>=', Carbon::now())
                                ->orWhere('start_date', '<', Carbon::now());
                            $indicators['active'] = "Active & Archive ({$query->count()})";
                        }

                        if ($data['active']) {
                            $query = $query->where('start_date', '>=', Carbon::now());
                            $indicators['active'] = "Active ({$query->count()})";
                        }
                        if ($data['archive']) {
                            $query = $query->where('start_date', '<', Carbon::now());
                            $indicators['archive'] = "Archive ({$query->count()})";
                        }
                        if ($data['country_id'] ?? null) {
                            $indicators['country_id'] = 'Country: ' . Country::find($data['country_id'])->name;
                        }
                        if ($data['city_id'] ?? null) {
                            $indicators['city_id'] = 'City: ' . City::find($data['city_id'])->name;
                        }
                        if ($data['company_id'] ?? null) {
                            $indicators['company_id'] = 'Company: ' . Company::find($data['company_id'])->name;
                        }
                        if ($data['created_by'] ?? null) {
                            $indicators['created_by'] = 'Admin creator: ' . User::find($data['created_by'])->name;
                        }
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Order from ' . Carbon::parse(
                                    $data['created_from']
                                )->toFormattedDateString();
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Order until ' . Carbon::parse(
                                    $data['created_until']
                                )->toFormattedDateString();
                        }

                        return $indicators;
                    })
            ], layout: FiltersLayout::AboveContent)
            ->columns([
                Columns\TextColumn::make('group_number')
                    ->searchable(),
                Columns\TextColumn::make('company.name')
                    ->numeric()
                    ->sortable(),
                Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('status')
                    ->badge(),
                Columns\TextColumn::make('total_price')
                    ->formatStateUsing(function($record, $state) {
                        /** @var Tour $record */
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($record->total_price) . ' ' . ExpenseService::getMainCurrency()?->from?->getSymbol();
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('expenses_total')
                    ->badge(fn(Tour $record) => TourService::isVisible($record))
                    ->color('danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->formatStateUsing(function($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state) . ' ' . ExpenseService::getMainCurrency()?->from?->getSymbol();
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('income')
                    ->badge(fn(Tour $record) => TourService::isVisible($record))
                    ->color(fn(Tour $record) => $record->income > 0 ? 'success' : 'danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->formatStateUsing(function($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state) . ' ' . ExpenseService::getMainCurrency()?->from?->getSymbol();
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('createdBy.name')
                    ->sortable(),
                Columns\TextColumn::make('createdBy.operator_percent_tps')
                    ->label('Operator %')
                    ->suffix('%')
                    ->sortable(),
                Columns\TextColumn::make('country.name'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                /*Columns\TextColumn::make('status')
                    ->numeric()
                    ->sortable(),
                Columns\TextColumn::make('pax')
                    ->numeric()
                    ->sortable(),*/
            ])
            ->recordUrl(null)
            ->recordAction(StatusAction::class)
            ->actions([
                /*Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('export_hotel')
                        ->label('Hotels')
                        ->icon('heroicon-o-document-text')
                        ->url(fn(Tour $record) => route('export-hotel', $record)),
                    Tables\Actions\Action::make('export_museum')
                        ->label('Museums')
                        ->icon('heroicon-o-document-text')
                        ->url(fn(Tour $record) => route('export-museum', $record)),
                    Tables\Actions\Action::make('export_client')
                        ->label('Client')
                        ->icon('heroicon-o-document-text')
                        ->url(fn(Tour $record) => route('export-client', $record)),
                    Tables\Actions\Action::make('export')
                        ->label('Report')
                        ->icon('heroicon-o-document-text')
                        ->url(fn(Tour $record) => route('export', $record)),
                ]),*/
                Tables\Actions\Action::make('export_all')
                    ->label('Reports')
                    ->icon('heroicon-o-document-text')
                    ->url(fn(Tour $record) => route('export-all', $record)),
                Tables\Actions\EditAction::make(),
                StatusAction::make()->label('')->icon(''),
            ], position: Tables\Enums\ActionsPosition::BeforeColumns)
            ->headerActions([

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin())
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DaysRelationManager::class,
            RelationManagers\ExpensesThroughDaysRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'edit' => Pages\EditTour::route('/{record}/edit')
        ];
    }
}
