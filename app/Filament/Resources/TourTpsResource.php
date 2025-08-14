<?php

namespace App\Filament\Resources;

use App\Enums\CompanyType;
use App\Enums\CurrencyEnum;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\GuideType;
use App\Enums\TourType;
use App\Enums\TransportType;
use App\Filament\Resources\TourTpsResource\Actions\StatusAction;
use App\Filament\Resources\TourTpsResource\Pages;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Tour;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Forms\Components;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class TourTpsResource extends Resource
{
    use InteractsWithForms;

    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'TPS';
    protected static ?string $slug = 'tour-tps';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('days.expenses.guides')
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
                        ->formatStateUsing(function ($record) {
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
                        ->afterStateUpdated(function ($get, $set) {
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
                    Components\DateTimePicker::make('end_date')
                        ->displayFormat('d.m.Y H:i')
                        ->label('Departure time')
//                        ->native(false)
                        ->seconds(false)
                        ->minDate(fn($get) => Carbon::parse($get('start_date'))->addDay())
                        ->reactive()
                        ->required(),
                    Components\TextInput::make('pax')
                        ->required()
                        ->numeric(),
                    Components\TextInput::make('leader_pax')
                        ->numeric(),
                ]),
                Components\Grid::make(4)->schema([
                    Components\TextInput::make('price')
                        ->label(fn($get) => 'Price (' . ($get('price_currency') ?? 'UZS') . ')')
                        ->suffixAction(
                            Components\Actions\Action::make('toggle-currency')
                                ->icon('heroicon-o-banknotes')
                                ->iconSize('md')
                                ->action(function ($get, $set) {
                                    $set('price_currency', $get('price_currency') != 'USD' ? 'USD' : 'UZS');
                                })
                        )
                        ->numeric(),
                    Components\TextInput::make('single_supplement_price')
                        ->numeric(),
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
                Components\Select::make('transport_type')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->options(TransportType::class),
                Components\TextInput::make('transport_price')
                    ->label(fn($get) => 'Price (' . ($get('transport_price_currency') ?? 'UZS') . ')')
                    ->suffixAction(
                        Components\Actions\Action::make('toggle-currency')
                            ->icon('heroicon-o-banknotes')
                            ->iconSize('md')
                            ->action(function ($get, $set) {
                                $set('transport_price_currency', $get('transport_price_currency') == 'USD' ? 'UZS' : 'USD');
                            })
                    )
                    ->numeric(),
            ]),

            Components\Fieldset::make('Rooming info')->schema([

                ...TourService::generateRoomingSchema(true),

                Section::make("Other rooming")
                    ->schema(TourService::generateRoomingSchema())
                    ->collapsible()
                    ->collapsed(),
            ]),

            Components\Repeater::make('days')
                ->lazy()
                ->extraAttributes(['class' => 'repeater-days'])
                ->collapsible()
                ->relationship('days')
                ->addActionLabel('Add day')
                ->columnSpanFull()
                ->cloneable()
                ->addActionAlignment('end')
                ->afterStateUpdated(function ($state, $get, $set) {
                    $prevDate = null;
                    foreach ($state as $uuid => $day) {
                        $date = $day['date'];
                        if (empty($date) && $prevDate) {
                            $set("days.$uuid.date", Carbon::parse($prevDate)->addDay()->format('Y-m-d'));
                        }

                        $prevDate = $date;
                    }

                    $prevDate = null;
                })
                ->itemLabel(function ($get, $set, $uuid) {
                    $current = Arr::get($get('days'), $uuid);
                    $index = array_search($uuid, array_keys($get('days'))) ?? 0;

                    $index++;
                    $date = $current['date'];
                    if ($date) {
                        $date = date('d.m.Y', strtotime($date));
                        return "Day $index ($date)";
                    }

                    return "Day $index";
                })
                ->schema([
                    Components\Grid::make(3)->schema([
                        Components\DatePicker::make('date')
                            ->displayFormat('d.m.Y')
                            ->minDate(fn($record) => $record ? $record->date : null)
                            ->native(false)
                            ->required()
                            ->reactive(),
                        Components\Select::make('city_id')
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->options(fn($get) => TourService::getCities())
                            ->afterStateUpdated(function ($get, $set) {
                                $days = $get('../');

                                $hotelsData = [];

                                $prevCityId = null;
                                foreach ($days as $uuidDay => $day) {
                                    $cityId = $day['city_id'];
                                    $expenses = $day['expenses'];

                                    foreach ($expenses as $uuid => $expense) {
                                        $hotelId = $expense['hotel_id'];
                                        if (empty($hotelId) && $cityId == $prevCityId && isset($hotelsData[$cityId])) {
                                            $set("expenses.$uuid.hotel_id", $hotelsData[$cityId]);
                                        }

                                        $hotelsData[$cityId] = $hotelId;
                                    }

                                    $prevCityId = $cityId;
                                }
                            })
                            ->reactive()
                            ->preload()
                            ->required(),
                    ]),
                    Components\Repeater::make('expenses')
                        ->lazy()
                        ->extraAttributes(['class' => 'repeater-expenses'])
                        ->collapsible()
                        ->cloneable()
                        ->collapsed(fn($record, $get, $state) => !empty($record->id))
                        ->itemLabel(function ($get, $uuid) {
                            $current = Arr::get($get('expenses'), $uuid);
                            $index = array_search($uuid, array_keys($get('expenses'))) ?? 0;
                            $index++;

                            $expenseType = $current['type'] ?? null;
                            if ($expenseType) {
                                $expenseTypeLabel = ExpenseType::from($expenseType)->getLabel();
                                $currStatus = $current['status'] ?? null;
                                $status = ($currStatus ? " - " . ExpenseStatus::from($currStatus)->getLabel() : '');
                                return "Expense for $expenseTypeLabel ($index)" . strtoupper($status);
                            }

                            return "Expense $index";
                        })
                        ->relationship('expenses')
                        ->addActionLabel('Add expense')
                        ->addActionAlignment('end')
                        ->schema([
                            Components\Grid::make()->schema([
                                Hidden::make('index'),
                                Hidden::make('price_currency'),
                                Components\Select::make('type')
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->label('Expense Type')
                                    ->options(function ($get) {
                                        $options = ExpenseType::casesOptions();
                                        unset($options[ExpenseType::Conference->value]);
                                        if ($get('../../../../guide_type') == GuideType::Escort->value) {
                                            unset($options[ExpenseType::Guide->value]);
                                        }
                                        return $options;
                                    })
                                    ->required()
                                    ->reactive(),
                                Components\Select::make('city_id')
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->options(fn($get) => TourService::getCities())
                                    ->reactive()
                                    ->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),
                            ]),

                            // Hotel
                            Components\Fieldset::make('Hotel info')->schema([
                                Components\Grid::make(4)->schema([
                                    Components\Select::make('hotel_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label('Hotel')
                                        ->options(
                                            fn($get) => TourService::getHotels($get('city_id') ?? $get('../../city_id'))
                                        )
                                        ->preload()
                                        ->reactive(),
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->label('Status'),
                                    Components\TimePicker::make('hotel_checkin_time')
                                        ->seconds(false)
                                        ->label('Check-in time'),
                                    Components\TimePicker::make('hotel_checkout_time')
                                        ->seconds(false)
                                        ->label('Check-out time'),
                                ]),
                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),

                            // Guide
                            Components\Fieldset::make('Guide info')->schema([
                                Components\Grid::make()->schema([

                                    Components\Repeater::make('guides')
                                        ->extraAttributes(['class' => 'repeater-guides'])
                                        ->columnSpanFull()
                                        ->addActionAlignment('end')
                                        ->relationship('guides')
                                        ->schema([
                                            Components\Grid::make()->schema([
                                                Components\TextInput::make('name')
                                                    ->label('Guide name'),
                                                PhoneInput::make('phone')
                                                    ->strictMode()
                                                    ->onlyCountries(['UZ'])
                                                    ->defaultCountry('UZ'),
                                            ])
                                        ]),

                                    Components\Grid::make(3)->schema([
                                        Components\Select::make('status')
                                            ->native(false)
                                            ->searchable()
                                            ->preload()
                                            ->options(ExpenseStatus::class)
                                            ->default(ExpenseStatus::New->value)
                                            ->required()
                                            ->label('Status'),

                                        self::getExpensePriceInput('Guide price'),

                                        Components\Textarea::make('comment')->label('Comment'),
                                    ]),
                                ]),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Guide->value),

                            // Transport
                            Components\Fieldset::make('Transport info')->schema([

                                Components\Grid::make(3)->schema([
                                    /*Components\Select::make('transport_driver_ids')
                                        ->label('Drivers')
                                        ->multiple()
                                        ->options(TourService::getDrivers())
                                        ->native(false)
                                        ->searchable()
                                        ->preload(),*/
                                    Components\TimePicker::make('transport_time')
                                        ->seconds(false),
                                    Components\TextInput::make('transport_place')
                                        ->label('Pickup location'),
                                    Components\TextInput::make('transport_route')
                                        ->label('Destination'),
                                ]),

                                Components\Grid::make(3)->schema([
                                    Components\Select::make('to_city_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label('City')
                                        ->options(TourService::getCities())
                                        ->reactive(),

                                    Components\Select::make('status')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->options(ExpenseStatus::class)
                                        ->required()
                                        ->label('Status'),

//                                    self::getExpensePriceInput('Sell price'),

                                    Components\Textarea::make('comment')
                                        ->label('Comment'),
                                ]),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                            // Museum
                            Components\Fieldset::make('Museum info')->schema([

                                Components\Grid::make(3)->schema([
                                    Components\Select::make('museum_ids')
                                        ->label('Museum')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->multiple()
                                        ->options(fn($get) => TourService::getMuseums($get('../../city_id')))
                                        ->createOptionAction(function () {
                                            return [
                                                'url' => route('museum.create'),
                                                'label' => 'Create museum',
                                            ];
                                        })
                                        ->suffixAction(function () {
                                            return [
                                                Components\Actions\Action::make('create_museum')
                                                    ->label('Create museum')
                                                    ->icon('heroicon-o-plus')
                                                    ->url(route('filament.admin.resources.museums.create'), true),
                                            ];
                                        })
                                        ->preload()
                                        ->reactive(),
                                    Components\Select::make('museum_item_ids')
                                        ->label('Museum Children')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->options(fn($get) => TourService::getMuseumItems($get('museum_ids')))
                                        ->multiple()
                                        ->preload()
                                        ->disabled(function ($get) {
                                            if (empty($get('museum_ids'))) {
                                                return true;
                                            }
                                            $museums = TourService::getMuseumsByIds($get('museum_ids'));
                                            return empty($museums);
                                        }),

                                    Components\Textarea::make('comment')->label('Comment'),
                                ]),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Museum->value),

                            // Lunch and Dinner
                            Components\Fieldset::make('Lunch / Dinner info')->schema([

                                Components\Grid::make(4)->schema([
                                    Components\Select::make('city_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->options(fn($get) => TourService::getCities())
                                        ->reactive(),

                                    Components\Select::make('restaurant_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label('Restaurant')
                                        ->options(
                                            fn($get) => TourService::getRestaurants(
                                                $get('city_id') ?? $get('../../city_id')
                                            )
                                        )
                                        ->reactive(),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label('Status'),

                                    Components\Textarea::make('comment')->label('Comment'),
                                ]),

                            ])->visible(fn($get) => self::isLunch($get('type'))),

                            // Train
                            Components\Fieldset::make('Train info')->schema([

                                Components\Grid::make(4)->schema([
                                    Components\Select::make('train_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label('Train')
                                        ->options(TourService::getTrains()),

                                    Components\Select::make('to_city_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label('City')
                                        ->options(TourService::getCities())
                                        ->reactive(),

                                    Components\TimePicker::make('departure_time')
                                        ->seconds(false)
                                        ->label('Departure time'),

                                    Components\TimePicker::make('arrival_time')
                                        ->seconds(false)
                                        ->label('Arrival time'),
                                ]),

                                Components\Grid::make(4)->schema([
                                    Components\TextInput::make('train_class_second')
                                        ->label('Second')
                                        ->numeric(),
                                    Components\TextInput::make('train_class_business')
                                        ->label('Business')
                                        ->numeric(),
                                    Components\TextInput::make('train_class_vip')
                                        ->label('VIP')
                                        ->numeric(),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label('Status'),
                                ]),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Train->value),

                            // Show
                            Components\Fieldset::make('Show info')->schema([

                                Components\Grid::make(3)->schema([
                                    Components\Select::make('show_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label('Show')
                                        ->options(fn($get) => TourService::getShows($get('../../city_id')))
                                        ->reactive()
                                        ->required(),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label('Status'),

                                    Components\Textarea::make('comment')->label('Comment'),
                                ]),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Show->value),

                            // Flight
                            Components\Fieldset::make('Flight info')->schema([

                                Components\Grid::make(3)->schema([
                                    self::getExpensePriceInput(),

                                    Components\TextInput::make('plane_route'),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label('Status'),
                                ]),

                                Components\Grid::make(3)->schema([
                                    Components\TimePicker::make('departure_time')
                                        ->seconds(false)
                                        ->label('Departure time'),

                                    Components\DateTimePicker::make('arrival_time')
                                        ->seconds(false)
                                        ->label('Arrival time'),

                                    Components\Textarea::make('comment')
                                        ->label('Comment'),
                                ]),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Flight->value),

                            // Extra
                            Components\Fieldset::make('Extra info')->schema([
                                Components\Grid::make(3)->schema([
                                    Components\TextInput::make('other_name')
                                        ->label('Name'),

                                    self::getExpensePriceInput(),

                                    Components\Textarea::make('comment')->label('Comment'),
                                ]),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Extra->value),

                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(function ($data, $get) {
                            $tourData = $get('../../');
                            $data['from_city_id'] = $get('city_id');
                            if ($data['type'] == ExpenseType::Transport) {
                                $data['price'] = $tourData['transport_price'] ?? 0;
                            }

                            return ExpenseService::mutateExpense(
                                data: $data,
                                totalPax: $tourData['pax'] + ($tourData['leader_pax'] ?? 0),
                                countryId: $tourData['country_id'],
                                roomAmounts: ExpenseService::getRoomingAmounts($tourData),
                                day: $get()
                            );
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function ($data, $get) {
                            $tourData = $get('../../');
                            $data['from_city_id'] = $get('city_id');
                            if ($data['type'] == ExpenseType::Transport) {
                                $data['price'] = $tourData['transport_price'] ?? 0;
                            }

                            return ExpenseService::mutateExpense(
                                data: $data,
                                totalPax: $tourData['pax'] + ($tourData['leader_pax'] ?? 0),
                                countryId: $tourData['country_id'],
                                roomAmounts: ExpenseService::getRoomingAmounts($tourData),
                                day: $get()
                            );
                        })
                ]),
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
                    ->action(function ($get, $set) {
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
            ->modifyQueryUsing(function ($query) {
                return $query
                    ->without('days', 'days.expenses')
                    ->with('company', 'createdBy', 'country');
            })
            ->striped()
            ->defaultSort('start_date', 'asc')
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->filters([
                Tables\Filters\Filter::make('country_id')
                    ->form([
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
                    ->query(function (Builder $query, $data) {
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
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
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
            ])
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
                Columns\TextColumn::make('price')
                    ->formatStateUsing(function ($record, $state) {
                        if (TourService::isVisible($record)) {
                            if ($record->price_currency == 'USD') {
                                $currency = ExpenseService::getCurrency(CurrencyEnum::USD);
                                if ($currency) {
                                    $state = $state * $currency->rate;
                                }
                            }
                            return TourService::formatMoney($state);
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('expenses_total')
                    ->badge(fn(Tour $record) => TourService::isVisible($record))
                    ->color('danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->formatStateUsing(function ($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state);
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('income')
                    ->badge(fn(Tour $record) => TourService::isVisible($record))
                    ->color(fn(Tour $record) => $record->income > 0 ? 'success' : 'danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->formatStateUsing(function ($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state);
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
//            RelationManagers\DaysRelationManager::class,
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
