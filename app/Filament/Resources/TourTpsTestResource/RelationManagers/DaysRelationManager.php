<?php

namespace App\Filament\Resources\TourTpsTestResource\RelationManagers;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\GuideType;
use App\Models\Tour;
use App\Models\TourDay;
use App\Models\TourDayExpense;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Forms\Components;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
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
                        ->minDate(fn($record) => $record ? $record->date : null)
                        ->formatStateUsing(function ($record) {
                            if ($record) {
                                return $record->date;
                            }

                            /** @var TourDay $lastDay */
                            $lastDay = $this->ownerRecord->days()->latest('date')->first();
                            if ($lastDay) {
                                return $lastDay->date->addDay();
                            }

                            return $this->ownerRecord->start_date;
                        })
                        ->native(false)
                        ->required(),
                    Components\Select::make('city_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->options(fn($get) => TourService::getCities())
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
                            Components\Hidden::make('index'),
                            Components\Hidden::make('price_currency'),
                            Components\Select::make('type')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->label('Expense Type')
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
                                    ->reactive()
                                    ->required(),
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
                                    ->default(ExpenseStatus::New->value)
                                    ->required()
                                    ->label('Status'),

//                                self::getExpensePriceInput('Sell price'),

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
                                    ->default(ExpenseStatus::New->value)
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
                                    ->default(ExpenseStatus::New->value)
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
                                    ->default(ExpenseStatus::New->value)
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
                                    ->default(ExpenseStatus::New->value)
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
                        /** @var Tour $tour */
                        $tour = $this->getOwnerRecord();
                        $data['from_city_id'] = $get('city_id');
                        return ExpenseService::mutateExpense(
                            data: $data,
                            totalPax: $tour->getTotalPax(),
                            countryId: $tour->country_id,
                            roomAmounts: $tour->roomTypes->mapWithKeys(
                                fn($roomType) => [$roomType->room_type_id => $roomType->amount]
                            ),
                            day: $get()
                        );
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function ($data, $get) {
                        /** @var Tour $tour */
                        $tour = $this->getOwnerRecord();
                        $data['from_city_id'] = $get('city_id');

                        return ExpenseService::mutateExpense(
                            data: $data,
                            totalPax: $tour->getTotalPax(),
                            countryId: $tour->country_id,
                            roomAmounts: $tour->roomTypes->mapWithKeys(
                                fn($roomType) => [$roomType->room_type_id => $roomType->amount]
                            ),
                            day: $get()
                        );
                    })
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

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with('city');
            })
            ->recordTitleAttribute('date')
            ->columns([
                Tables\Columns\TextColumn::make('day')
                    ->label('Day')
                    ->state(fn($rowLoop) => $rowLoop->index + 1),
                Tables\Columns\TextColumn::make('date')
                    ->formatStateUsing(fn($state) => $state ? $state->format('d.m.Y') : null),
                Tables\Columns\TextColumn::make('city.name'),

                Tables\Columns\TextColumn::make('hotel')
                    ->label('Hotel')
                    ->getStateUsing(function ($record) {
                        /** @var TourDayExpense $hotelExpense */
                        $hotelExpense = $record->expenses()->where('type', ExpenseType::Hotel)->first();

                        return view('filament.columns.status-column', [
                            'name' => $hotelExpense?->hotel?->name,
                            'status' => $hotelExpense?->status,
                        ]);
                    }),

                Tables\Columns\TextColumn::make('guide')
                    ->label('Guide')
                    ->getStateUsing(function ($record) {

                        if ($record->tour->guide_type == GuideType::Escort) {
                            $guideName = $record->tour->guide_name;
                            $guideStatus = ExpenseStatus::Confirmed;
                        } else {
                            /** @var TourDayExpense $expense */
                            $expense = $record->expenses()->where('type', ExpenseType::Guide)->first();
                            // TODO: Guide
                            $guideName = $expense?->guides->map(fn($guide) => $guide->name)->join(', ');
                            $guideStatus = $expense?->status;
                        }

                        return view('filament.columns.status-column', [
                            'name' => $guideName,
                            'status' => $guideStatus,
                        ]);
                    }),

                Tables\Columns\TextColumn::make('plane')
                    ->label('Flight')
                    ->getStateUsing(function ($record) {
                        /** @var TourDayExpense $planeExpense */
                        $planeExpense = $record->expenses()->where('type', ExpenseType::Flight)->first();

                        return view('filament.columns.status-column', [
                            'name' => '',
                            'status' => $planeExpense?->status,
                        ]);
                    }),

                Tables\Columns\TextColumn::make('lunch')
                    ->label('Lunch')
                    ->getStateUsing(function ($record) {
                        /** @var TourDayExpense $lunchExpense */
                        $lunchExpense = $record->expenses()->where('type', ExpenseType::Lunch)->first();

                        return view('filament.columns.status-column', [
                            'name' => $lunchExpense?->restaurant?->name,
                            'status' => $lunchExpense?->status,
                        ]);
                    }),

                Tables\Columns\TextColumn::make('dinner')
                    ->label('Dinner')
                    ->getStateUsing(function ($record) {
                        /** @var TourDayExpense $lunchExpense */
                        $lunchExpense = $record->expenses()->where('type', ExpenseType::Dinner)->first();

                        return view('filament.columns.status-column', [
                            'name' => $lunchExpense?->restaurant?->name,
                            'status' => $lunchExpense?->status,
                        ]);
                    }),

                Tables\Columns\TextColumn::make('extra')
                    ->label('Extra')
                    ->getStateUsing(function ($record) {
                        /** @var TourDayExpense $extraExpense */
                        $extraExpense = $record->expenses()->where('type', ExpenseType::Extra)->first();

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
                    ->modalHeading("Add day")
                    ->after(function () {
                        /** @var Tour $tour */
                        $tour = $this->getOwnerRecord();
                        $tour->saveExpensesTotal();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading(fn($record) => "Edit day {$record->date->format('d.m.Y')}")
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
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
