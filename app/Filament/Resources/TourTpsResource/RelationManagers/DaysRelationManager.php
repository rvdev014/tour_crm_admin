<?php

namespace App\Filament\Resources\TourTpsResource\RelationManagers;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\GuideType;
use App\Enums\PlaneType;
use App\Models\Route;
use App\Models\Tour;
use App\Models\TourDay;
use App\Models\Transfer;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Forms\Components;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class DaysRelationManager extends RelationManager
{
    protected static string $relationship = 'days';

    protected static $daysIndex = 0;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Grid::make(3)->schema([
                    Components\DatePicker::make('date')
                        ->displayFormat('d.m.Y')
                        ->minDate(fn ($record) => $record ? $record->date : null)
                        ->formatStateUsing(function ($record) {
                            if ($record) {
                                return $record->date;
                            }

                            /** @var TourDay $lastDay */
                            $lastDay = $this->ownerRecord->daysNormal()->latest('date')->first();
                            if ($lastDay) {
                                return $lastDay->date->addDay();
                            }

                            return $this->ownerRecord->start_date;
                        })
                        ->native(false)
                        ->required(),
                    Components\Select::make('city_id')
                        ->label(__('City'))
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(fn ($get) => TourService::getCities())
                        ->default(function ($record) {
                            if ($record) {
                                return $record->city_id;
                            }

                            /** @var TourDay $lastDay */
                            $lastDay = $this->ownerRecord->days()->latest('date')->first();

                            return $lastDay?->city_id;
                        })
                        ->reactive()
                        ->preload()
                        ->required(),
                ]),

                Components\Repeater::make('expenses')
                    ->extraAttributes(['class' => 'repeater-expenses'])
                    ->collapsible()
                    ->cloneable()
                    ->columnSpanFull()
                    ->lazy()
                    ->collapsed(fn ($record, $get, $state) => ! empty($record->id))
                    ->itemLabel(function ($get, $uuid) {
                        $current = Arr::get($get('expenses'), $uuid);
                        $index = array_search($uuid, array_keys($get('expenses'))) ?? 0;
                        $index++;

                        $expenseType = $current['type'] ?? null;
                        if ($expenseType) {
                            $expenseTypeLabel = ExpenseType::from($expenseType)->getLabel();
                            $currStatus = $current['status'] ?? null;
                            $status = ($currStatus ? ' · '.ExpenseStatus::from($currStatus)->getLabel() : '');

                            return "$expenseTypeLabel #$index".$status;
                        }

                        return "Expense $index";
                    })
                    ->relationship('expenses')
                    ->addActionLabel(__('Add expense'))
                    ->addActionAlignment('end')
                    ->schema([
                        Components\Grid::make()->schema([
                            Components\Hidden::make('index'),
                            Components\Hidden::make('price_currency'),
                            Components\Select::make('type')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->label(__('Expense Type'))
                                ->options(function ($get) {
                                    $options = ExpenseType::casesOptions();
                                    unset($options[ExpenseType::Conference->value]);
                                    if ($this->ownerRecord->guide_type == GuideType::Escort) {
                                        unset($options[ExpenseType::Guide->value]);
                                    }

                                    return $options;
                                })
                                ->required()
                                ->reactive(),
                            Components\Select::make('city_id')
                                ->native(false)
                                ->label(function ($get) {
                                    if ($get('type') == ExpenseType::Train->value) {
                                        return 'City from';
                                    }

                                    return 'City';
                                })
                                ->searchable()
                                ->preload()
                                ->options(fn ($get) => TourService::getCities())
                                ->formatStateUsing(function ($get) {
                                    return $get('../../city_id') ?? null;
                                })
                                ->reactive(),
                            //                                ->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),
                        ]),

                        // Hotel
                        Components\Section::make(__('Hotel info'))
                            ->icon('heroicon-o-building-office-2')
                            ->description(__('Pick the hotel and stay dates. Total nights drives day auto-creation and cost.'))
                            ->schema([
                                Components\Grid::make(4)->schema([
                                    Components\Select::make('hotel_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Hotel'))
                                        ->options(
                                            fn ($get) => TourService::getHotels($get('city_id') ?? $get('../../city_id'))
                                        )
                                        ->preload()
                                        ->reactive()
                                        ->required(),
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->label(__('Status')),
                                    Components\TimePicker::make('hotel_checkin_time')
                                        ->native(false)
                                        ->seconds(false)
                                        ->label(__('Check-in time'))
                                        ->reactive()
                                        ->afterStateUpdated(function ($get, $set) {
                                            $set('hotel_total_nights', TourService::calculateHotelNights(
                                                $get('hotel_id'),
                                                $get('../../date'),
                                                $get('hotel_checkin_time'),
                                                $get('hotel_checkout_date_time')
                                            ));
                                        }),
                                    Components\DateTimePicker::make('hotel_checkout_date_time')
                                        ->native(false)
                                        ->seconds(false)
                                        ->label(__('Check-out date & time'))
                                        ->reactive()
                                        ->afterStateUpdated(function ($get, $set) {
                                            $set('hotel_total_nights', TourService::calculateHotelNights(
                                                $get('hotel_id'),
                                                $get('../../date'),
                                                $get('hotel_checkin_time'),
                                                $get('hotel_checkout_date_time')
                                            ));
                                        }),
                                ]),
                                Components\Grid::make(4)->schema([
                                    Components\TextInput::make('hotel_total_nights')
                                        ->label(__('Total nights'))
                                        ->numeric(),
                                ]),
                                Components\Textarea::make('comment')
                                    ->label(__('Comment'))
                                    ->columnSpanFull(),
                            ])->visible(fn ($get) => $get('type') == ExpenseType::Hotel->value),

                        // Guide
                        Components\Section::make(__('Guide info'))
                            ->icon('heroicon-o-identification')
                            ->description(__('Assign one or more guides for this expense.'))
                            ->schema([
                                Components\Grid::make()->schema([

                                    Components\Repeater::make('guides')
                                        ->extraAttributes(['class' => 'repeater-guides'])
                                        ->columnSpanFull()
                                        ->addActionAlignment('end')
                                        ->relationship('guides')
                                        ->schema([
                                            Components\Grid::make()->schema([
                                                Components\TextInput::make('name')
                                                    ->label(__('Guide name')),
                                                PhoneInput::make('phone')
                                                    ->strictMode()
                                                    ->onlyCountries(['UZ'])
                                                    ->defaultCountry('UZ'),
                                            ]),
                                        ]),

                                    Components\Grid::make(3)->schema([
                                        Components\Select::make('status')
                                            ->native(false)
                                            ->searchable()
                                            ->preload()
                                            ->options(ExpenseStatus::class)
                                            ->default(ExpenseStatus::New->value)
                                            ->required()
                                            ->label(__('Status')),

                                        self::getExpensePriceInput('Guide price'),

                                        Components\Textarea::make('comment')->label(__('Comment')),
                                    ]),
                                ]),
                            ])->visible(fn ($get) => $get('type') == ExpenseType::Guide->value),

                        // Transport
                        Components\Section::make(__('Transport info'))
                            ->icon('heroicon-o-truck')
                            ->description(__('Vehicle class, route, and pickup time for this transfer.'))
                            ->schema([

                                Components\Hidden::make('price_currency'),

                                Components\Grid::make(3)->schema([
                                    Components\TimePicker::make('transport_time')
                                        ->seconds(false),
                                    Components\Select::make('transport_class_id')
                                        ->label(__('Transport class'))
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->options(fn () => TourService::getTransportClasses())
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, $set) {
                                            $set('route_id', null);
                                            $set('price', null);
                                        }),
                                    Components\Select::make('route_id')
                                        ->label(__('Route'))
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->options(fn ($get) => $get('transport_class_id')
                                            ? TourService::getRoutesForTransportClass((int) $get('transport_class_id'))
                                            : []
                                        )
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, $get, $set) {
                                            if ($state && $get('transport_class_id')) {
                                                $price = TourService::getRoutePriceForTransportClass(
                                                    (int) $state,
                                                    (int) $get('transport_class_id')
                                                );
                                                if ($price !== null) {
                                                    $set('price', $price);
                                                    $set('price_currency', 'USD');
                                                }
                                                $route = Route::with('waypoints.city')->find($state);
                                                if ($route) {
                                                    $set('transport_route', $route->display_name);
                                                }
                                            }
                                        }),
                                ]),

                                Components\Grid::make(3)->schema([
                                    Components\TextInput::make('transport_route')
                                        ->label(__('Destination'))
                                        ->required(),
                                    Components\Select::make('to_city_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('City to'))
                                        ->options(TourService::getCities())
                                        ->reactive(),

                                    Components\Select::make('status')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->required()
                                        ->label(__('Status')),
                                ]),

                                Components\Grid::make(3)->schema([
                                    Components\TextInput::make('price')
                                        ->label(fn ($get) => 'Price ('.($get('price_currency') ?? 'USD').')')
                                        ->numeric()
                                        ->reactive(),
                                    Components\Textarea::make('comment')
                                        ->label(__('Comment')),
                                ]),

                            ])->visible(fn ($get) => $get('type') == ExpenseType::Transport->value),

                        // Museum
                        Components\Section::make(__('Museum info'))
                            ->icon('heroicon-o-building-library')
                            ->description(__('Select the museums (and optionally specific exhibits) for this visit.'))
                            ->schema([

                                Components\Grid::make(4)->schema([
                                    Components\Select::make('museum_ids')
                                        ->label(__('Museum'))
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->multiple()
                                        ->options(fn ($get) => TourService::getMuseums($get('../../city_id')))
                                        ->createOptionAction(function () {
                                            return [
                                                'url' => route('museum.create'),
                                                'label' => 'Create museum',
                                            ];
                                        })
                                        ->suffixAction(function () {
                                            return [
                                                Components\Actions\Action::make('create_museum')
                                                    ->label(__('Create museum'))
                                                    ->icon('heroicon-o-plus')
                                                    ->url(route('filament.admin.resources.museums.create'), true),
                                            ];
                                        })
                                        ->preload()
                                        ->reactive(),
                                    Components\Select::make('museum_item_ids')
                                        ->label(__('Museum Children'))
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->options(fn ($get) => TourService::getMuseumItems($get('museum_ids')))
                                        ->multiple()
                                        ->preload()
                                        ->disabled(function ($get) {
                                            if (empty($get('museum_ids'))) {
                                                return true;
                                            }
                                            $museums = TourService::getMuseumsByIds($get('museum_ids'));

                                            return empty($museums);
                                        }),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Status')),

                                    Components\Textarea::make('comment')->label(__('Comment')),
                                ]),

                            ])->visible(fn ($get) => $get('type') == ExpenseType::Museum->value),

                        // Lunch and Dinner
                        Components\Section::make(__('Lunch / Dinner info'))
                            ->icon('heroicon-o-cake')
                            ->description(__('Pick the restaurant for this meal.'))
                            ->schema([

                                Components\Grid::make(4)->schema([
                                    Components\Select::make('city_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->options(fn ($get) => TourService::getCities())
                                        ->reactive(),

                                    Components\Select::make('restaurant_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Restaurant'))
                                        ->options(
                                            fn ($get) => TourService::getRestaurants(
                                                $get('city_id') ?? $get('../../city_id')
                                            )
                                        )
                                        ->reactive(),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Status')),

                                    Components\Textarea::make('comment')->label(__('Comment')),
                                ]),

                            ])->visible(fn ($get) => self::isLunch($get('type'))),

                        // Train
                        Components\Section::make(__('Train info'))
                            ->icon('heroicon-o-ticket')
                            ->description(__('Train, route, and class seat counts.'))
                            ->schema([

                                Components\Grid::make(4)->schema([
                                    Components\Select::make('train_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Train'))
                                        ->options(TourService::getTrains()),

                                    Components\Select::make('to_city_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('City to'))
                                        ->options(TourService::getCities())
                                        ->reactive(),

                                    Components\TimePicker::make('departure_time')
                                        ->seconds(false)
                                        ->label(__('Departure time')),

                                    Components\DateTimePicker::make('arrival_time')
                                        ->seconds(false)
                                        ->label(__('Arrival time')),
                                ]),

                                Components\Grid::make(4)->schema([
                                    Components\TextInput::make('train_class_second')
                                        ->label(__('Second'))
                                        ->numeric(),
                                    Components\TextInput::make('train_class_business')
                                        ->label(__('Business'))
                                        ->numeric(),
                                    Components\TextInput::make('train_class_vip')
                                        ->label(__('VIP'))
                                        ->numeric(),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->formatStateUsing(fn ($state, $get) => $get('id') ? $get('status') : ExpenseStatus::Confirmed->value)
                                        ->reactive()
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload(),
                                ]),

                                Components\Textarea::make('comment')
                                    ->label(__('Comment'))
                                    ->columnSpanFull(),

                            ])->visible(fn ($get) => $get('type') == ExpenseType::Train->value),

                        // Show
                        Components\Section::make(__('Show info'))
                            ->icon('heroicon-o-film')
                            ->description(__('Pick the show for this evening.'))
                            ->schema([

                                Components\Grid::make(3)->schema([
                                    Components\Select::make('show_id')
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Show'))
                                        ->options(fn ($get) => TourService::getShows($get('../../city_id')))
                                        ->reactive()
                                        ->required(),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Status')),

                                    Components\Textarea::make('comment')->label(__('Comment')),
                                ]),

                            ])->visible(fn ($get) => $get('type') == ExpenseType::Show->value),

                        // Flight
                        Components\Section::make(__('Flight info'))
                            ->icon('heroicon-o-paper-airplane')
                            ->description(__('Flight route, timings, and service fee.'))
                            ->schema([

                                Components\Grid::make(4)->schema([
                                    self::getExpensePriceInput(),

                                    Components\TextInput::make('plane_route'),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Status')),

                                    Components\Textarea::make('comment')
                                        ->label(__('Comment')),
                                ]),

                                Components\Grid::make(4)->schema([
                                    Components\TimePicker::make('departure_time')
                                        ->seconds(false)
                                        ->label(__('Departure time')),

                                    Components\TextInput::make('departure_number')
                                        ->label(__('Departure reys number')),

                                    Components\DateTimePicker::make('arrival_time')
                                        ->seconds(false)
                                        ->label(__('Arrival time')),

                                    Components\TextInput::make('arrival_number')
                                        ->label(__('Arrival reys number')),
                                ]),

                                Components\Grid::make(4)->schema([
                                    Components\Select::make('plane_type')
                                        ->options(PlaneType::class)
                                        ->label(__('Plane type')),

                                    Components\TextInput::make('plane_service_fee')
                                        ->label(__('Service fee')),
                                ]),

                            ])->visible(fn ($get) => $get('type') == ExpenseType::Flight->value),

                        // Extra
                        Components\Section::make(__('Extra info'))
                            ->icon('heroicon-o-plus-circle')
                            ->description(__('Any other one-off cost for this day.'))
                            ->schema([
                                Components\Grid::make(4)->schema([
                                    Components\TextInput::make('other_name')
                                        ->label(__('Name')),

                                    self::getExpensePriceInput(),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->default(ExpenseStatus::New->value)
                                        ->required()
                                        ->native(false)
                                        ->searchable()
                                        ->preload()
                                        ->label(__('Status')),

                                    Components\Textarea::make('comment')->label(__('Comment')),
                                ]),
                            ])->visible(fn ($get) => $get('type') == ExpenseType::Extra->value),

                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(function ($data, $get) {
                        /** @var Tour $tour */
                        $tour = $this->getOwnerRecord();
                        $data['from_city_id'] = $get('city_id');

                        // Set default status for Train expenses to Confirmed
                        if ($data['type'] == ExpenseType::Train->value && empty($data['status'])) {
                            $data['status'] = ExpenseStatus::Confirmed->value;
                        }

                        $data = ExpenseService::mutateExpense(
                            data: $data,
                            totalPax: $tour->getTotalPax(),
                            countryId: $tour->country_id,
                            roomAmounts: ExpenseService::getRoomingAmountsFromTour($tour),
                            day: $get(),
                            isTps: true
                        );

                        $this->ensureFollowingDaysForHotelExpense($tour, $data, $get);

                        return $data;
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function ($data, $get) {
                        /** @var Tour $tour */
                        $tour = $this->getOwnerRecord();
                        $data['from_city_id'] = $get('city_id');

                        // Set default status for Train expenses to Confirmed
                        if ($data['type'] == ExpenseType::Train->value && empty($data['status'])) {
                            $data['status'] = ExpenseStatus::Confirmed->value;
                        }

                        $data = ExpenseService::mutateExpense(
                            data: $data,
                            totalPax: $tour->getTotalPax(),
                            countryId: $tour->country_id,
                            roomAmounts: ExpenseService::getRoomingAmountsFromTour($tour),
                            day: $get(),
                            isTps: true
                        );

                        $this->ensureFollowingDaysForHotelExpense($tour, $data, $get);

                        return $data;
                    }),
            ]);
    }

    /**
     * When a Hotel expense spans more than one night, auto-creates the
     * following TourDay rows (same city, no expenses) so the operator
     * doesn't have to add each night's day by hand. Skips any date that
     * already has a day.
     */
    protected function ensureFollowingDaysForHotelExpense(Tour $tour, array $data, $get): void
    {
        if (($data['type'] ?? null) != ExpenseType::Hotel->value) {
            return;
        }

        $nights = (float) ($data['hotel_total_nights'] ?? 0);
        $checkinDate = $get('../../date');
        if ($nights > 1 && $checkinDate) {
            TourService::ensureFollowingDaysExist(
                $tour,
                Carbon::parse($checkinDate),
                $nights,
                $get('../../city_id')
            );
        }
    }

    public static function getExpensePriceInput(string $label = 'Price'): Components\TextInput
    {
        return Components\TextInput::make('price')
            ->label(fn ($get) => "$label (".($get('price_currency') ?? 'UZS').')')
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

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['city', 'expenses']);
            })
            ->recordTitleAttribute('date')
            ->columns([
                Tables\Columns\TextColumn::make('day')
                    ->label(__('Day'))
                    ->state(fn ($rowLoop) => $rowLoop->index + 1),
                Tables\Columns\TextColumn::make('date')
                    ->formatStateUsing(fn ($state) => $state ? $state->format('d.m.Y') : null),
                Tables\Columns\TextColumn::make('city.name'),

                Tables\Columns\TextColumn::make('hotel')
                    ->label(__('Hotel'))
                    ->getStateUsing(function (TourDay $record) {
                        $hotelExpense = $record->getExpense(ExpenseType::Hotel);

                        return view('filament.columns.status-column', [
                            'name' => $hotelExpense?->hotel?->name,
                            'status' => $hotelExpense?->status,
                        ]);
                    }),

                Tables\Columns\TextColumn::make('guide')
                    ->label(__('Guide'))
                    ->getStateUsing(function (TourDay $record) {

                        if ($record->tour->guide_type == GuideType::Escort) {
                            $guideName = $record->tour->guide_name;
                            $guideStatus = ExpenseStatus::Confirmed;
                        } else {
                            $expense = $record->getExpense(ExpenseType::Guide);
                            // TODO: Guide
                            $guideName = $expense?->guides->map(fn ($guide) => $guide->name)->join(', ');
                            $guideStatus = $expense?->status;
                        }

                        return view('filament.columns.status-column', [
                            'name' => $guideName,
                            'status' => $guideStatus,
                        ]);
                    }),

                Tables\Columns\TextColumn::make('plane')
                    ->label(__('Flight'))
                    ->getStateUsing(function (TourDay $record) {
                        $planeExpense = $record->getExpense(ExpenseType::Flight);

                        return view('filament.columns.status-column', [
                            'name' => $planeExpense?->plane_route,
                            'status' => $planeExpense?->status,
                            'content' => "<p>$planeExpense?->departure_time</p>",
                        ]);
                    }),

                Tables\Columns\TextColumn::make('train')
                    ->label(__('Train'))
                    ->getStateUsing(function (TourDay $record) {
                        $trainExpense = $record->getExpense(ExpenseType::Train);

                        return view('filament.columns.status-column', [
                            'name' => $trainExpense?->train?->name,
                            'status' => $trainExpense?->status,
                            'content' => '<p>'.($trainExpense?->arrival_time ? \Carbon\Carbon::parse($trainExpense->arrival_time)->format('d-m H:i') : '')." - {$trainExpense?->departure_time}</p>",
                        ]);
                    }),

                Tables\Columns\TextColumn::make('transport')
                    ->label(__('Transfer'))
                    ->getStateUsing(function (TourDay $record) {
                        $transportExpense = $record->getExpense(ExpenseType::Transport);
                        /** @var Transfer $transfer */
                        $transfer = Transfer::query()->where('tour_day_expense_id', $transportExpense?->id)->first();

                        return view('filament.columns.status-column', [
                            'name' => $transfer?->number,
                            'status' => '',
                        ]);
                    }),

                Tables\Columns\TextColumn::make('lunch')
                    ->label(__('Lunch'))
                    ->getStateUsing(function (TourDay $record) {
                        $lunchExpense = $record->getExpense(ExpenseType::Lunch);

                        return view('filament.columns.status-column', [
                            'name' => $lunchExpense?->restaurant?->name,
                            'status' => $lunchExpense?->status,
                        ]);
                    }),

                Tables\Columns\TextColumn::make('dinner')
                    ->label(__('Dinner'))
                    ->getStateUsing(function (TourDay $record) {
                        $lunchExpense = $record->getExpense(ExpenseType::Dinner);

                        return view('filament.columns.status-column', [
                            'name' => $lunchExpense?->restaurant?->name,
                            'status' => $lunchExpense?->status,
                        ]);
                    }),

                Tables\Columns\TextColumn::make('extra')
                    ->label(__('Extra'))
                    ->getStateUsing(function (TourDay $record) {
                        $extraExpense = $record->getExpense(ExpenseType::Extra);

                        return view('filament.columns.status-column', [
                            'name' => $extraExpense?->other_name,
                            'status' => $extraExpense?->status,
                        ]);
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading(__('Add day'))
                    ->modalWidth('5xl')
                    ->after(function () {
                        /** @var Tour $tour */
                        $tour = $this->getOwnerRecord();
                        $tour->saveExpensesTotal();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading(fn ($record) => "Edit day {$record->date->format('d.m.Y')}")
                    ->modalWidth('5xl')
                    ->after(function () {
                        /** @var Tour $tour */
                        $tour = $this->getOwnerRecord();
                        $tour->saveExpensesTotal();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        /** @var Tour $tour */
                        $tour = $this->getOwnerRecord();
                        $tour->saveExpensesTotal();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn () => auth()->user()->isAdmin()),
                ]),
            ]);
    }
}
