<?php

namespace App\Filament\Resources\TourTpsResource\RelationManagers;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\GuideType;
use App\Enums\PlaneType;
use App\Models\Route;
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
use Illuminate\Support\Carbon;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class ExpensesThroughDaysRelationManager extends RelationManager
{
    protected static string $relationship = 'expensesThroughDays';

    protected static ?string $title = 'Expenses';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Grid::make()->schema([
                    Components\Hidden::make('index'),
                    Components\Hidden::make('price_currency'),
                    Components\Select::make('tour_day_id')
                        ->label('Day')
                        ->options(function ($get) {
                            $options = [];
                            foreach ($this->ownerRecord->days as $day) {
                                $options[$day->id] = $day->date->format('d.m.Y');
                            }

                            return $options;
                        })
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive(),
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
                        ->label(function ($get) {
                            if ($get('type') == ExpenseType::Train->value) {
                                return 'City from';
                            }

                            return 'City';
                        })
                        ->searchable()
                        ->preload()
                        ->options(fn ($get) => TourService::getCities())
                        ->reactive()
                        ->visible(fn ($get) => $get('type') == ExpenseType::Hotel->value),
                ]),

                // Hotel
                Components\Section::make('Hotel info')
                    ->icon('heroicon-o-building-office-2')
                    ->description('Pick the hotel and stay dates. Total nights drives day auto-creation and cost.')
                    ->schema([
                        Components\Grid::make(4)->schema([
                            Components\Select::make('hotel_id')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->label('Hotel')
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
                                ->label('Status'),
                            Components\TimePicker::make('hotel_checkin_time')
                                ->native(false)
                                ->seconds(false)
                                ->label('Check-in time')
                                ->reactive()
                                ->afterStateUpdated(function ($get, $set) {
                                    $set('hotel_total_nights', TourService::calculateHotelNights(
                                        $get('hotel_id'),
                                        TourDay::find($get('tour_day_id'))?->date,
                                        $get('hotel_checkin_time'),
                                        $get('hotel_checkout_date_time')
                                    ));
                                }),
                            Components\DateTimePicker::make('hotel_checkout_date_time')
                                ->native(false)
                                ->seconds(false)
                                ->label('Check-out date & time')
                                ->reactive()
                                ->afterStateUpdated(function ($get, $set) {
                                    $set('hotel_total_nights', TourService::calculateHotelNights(
                                        $get('hotel_id'),
                                        TourDay::find($get('tour_day_id'))?->date,
                                        $get('hotel_checkin_time'),
                                        $get('hotel_checkout_date_time')
                                    ));
                                }),
                        ]),
                        Components\Grid::make(4)->schema([
                            Components\TextInput::make('hotel_total_nights')
                                ->label('Total nights')
                                ->numeric(),
                        ]),
                        Components\Textarea::make('comment')
                            ->label('Comment')
                            ->columnSpanFull(),
                    ])->visible(fn ($get) => $get('type') == ExpenseType::Hotel->value),

                // Guide
                Components\Section::make('Guide info')
                    ->icon('heroicon-o-identification')
                    ->description('Assign one or more guides for this expense.')
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
                                            ->label('Guide name'),
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
                                    ->label('Status'),

                                self::getExpensePriceInput('Guide price'),

                                Components\Textarea::make('comment')->label('Comment'),
                            ]),
                        ]),
                    ])->visible(fn ($get) => $get('type') == ExpenseType::Guide->value),

                // Transport
                Components\Section::make('Transport info')
                    ->icon('heroicon-o-truck')
                    ->description('Vehicle class, route, and pickup time for this transfer.')
                    ->schema([

                        Components\Hidden::make('price_currency'),

                        Components\Grid::make(3)->schema([
                            Components\TimePicker::make('transport_time')
                                ->seconds(false),
                            Components\Select::make('transport_class_id')
                                ->label('Transport class')
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
                                ->label('Route')
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
                                ->label('Destination'),
                            Components\Select::make('to_city_id')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->label('City to')
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
                        ]),

                        Components\Grid::make(3)->schema([
                            Components\TextInput::make('price')
                                ->label(fn ($get) => 'Price ('.($get('price_currency') ?? 'USD').')')
                                ->numeric()
                                ->reactive(),
                            Components\Textarea::make('comment')
                                ->label('Comment'),
                        ]),

                    ])->visible(fn ($get) => $get('type') == ExpenseType::Transport->value),

                // Museum
                Components\Section::make('Museum info')
                    ->icon('heroicon-o-building-library')
                    ->description('Select the museums (and optionally specific exhibits) for this visit.')
                    ->schema([

                        Components\Grid::make(4)->schema([
                            Components\Select::make('museum_ids')
                                ->label('Museum')
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
                                ->label('Status'),

                            Components\Textarea::make('comment')->label('Comment'),
                        ]),

                    ])->visible(fn ($get) => $get('type') == ExpenseType::Museum->value),

                // Lunch and Dinner
                Components\Section::make('Lunch / Dinner info')
                    ->icon('heroicon-o-cake')
                    ->description('Pick the restaurant for this meal.')
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
                                ->label('Restaurant')
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
                                ->label('Status'),

                            Components\Textarea::make('comment')->label('Comment'),
                        ]),

                    ])->visible(fn ($get) => self::isLunch($get('type'))),

                // Train
                Components\Section::make('Train info')
                    ->icon('heroicon-o-ticket')
                    ->description('Train, route, and class seat counts.')
                    ->schema([

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
                                ->label('City to')
                                ->options(TourService::getCities())
                                ->reactive(),

                            Components\TimePicker::make('departure_time')
                                ->seconds(false)
                                ->label('Departure time'),

                            Components\DateTimePicker::make('arrival_time')
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
                                ->formatStateUsing(fn ($state, $get) => $get('id') ? $get('status') : ExpenseStatus::Confirmed->value)
                                ->required()
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->label('Status'),
                        ]),

                        Components\Textarea::make('comment')
                            ->label('Comment')
                            ->columnSpanFull(),

                    ])->visible(fn ($get) => $get('type') == ExpenseType::Train->value),

                // Show
                Components\Section::make('Show info')
                    ->icon('heroicon-o-film')
                    ->description('Pick the show for this evening.')
                    ->schema([

                        Components\Grid::make(3)->schema([
                            Components\Select::make('show_id')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->label('Show')
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
                                ->label('Status'),

                            Components\Textarea::make('comment')->label('Comment'),
                        ]),

                    ])->visible(fn ($get) => $get('type') == ExpenseType::Show->value),

                // Flight
                Components\Section::make('Flight info')
                    ->icon('heroicon-o-paper-airplane')
                    ->description('Flight route, timings, and service fee.')
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
                                ->label('Status'),

                            Components\Textarea::make('comment')
                                ->label('Comment'),
                        ]),

                        Components\Grid::make(4)->schema([
                            Components\TimePicker::make('departure_time')
                                ->seconds(false)
                                ->label('Departure time'),

                            Components\TextInput::make('departure_number')
                                ->label('Departure reys number'),

                            Components\DateTimePicker::make('arrival_time')
                                ->seconds(false)
                                ->label('Arrival time'),

                            Components\TextInput::make('arrival_number')
                                ->label('Arrival reys number'),
                        ]),

                        Components\Grid::make(4)->schema([
                            Components\Select::make('plane_type')
                                ->options(PlaneType::class)
                                ->label('Plane type'),

                            Components\TextInput::make('plane_service_fee')
                                ->label('Service fee'),
                        ]),

                    ])->visible(fn ($get) => $get('type') == ExpenseType::Flight->value),

                // Extra
                Components\Section::make('Extra info')
                    ->icon('heroicon-o-plus-circle')
                    ->description('Any other one-off cost for this day.')
                    ->schema([
                        Components\Grid::make(3)->schema([
                            Components\TextInput::make('other_name')
                                ->label('Name'),

                            self::getExpensePriceInput(),

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
                    ])->visible(fn ($get) => $get('type') == ExpenseType::Extra->value),

            ]);
    }

    public static function isLunch($expenseType): bool
    {
        return in_array($expenseType, [ExpenseType::Lunch->value, ExpenseType::Dinner->value]);
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

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'asc')
            ->recordTitleAttribute('tourDay.date')
            ->defaultGroup(
                Tables\Grouping\Group::make('tourDay.date')
                    ->label('Day')
                    ->getTitleFromRecordUsing(function (TourDayExpense $record) {
                        return $record->tourDay->date->format('d.m.Y');
                    })
                    ->collapsible(),
            )
            ->columns([
                //                Tables\Columns\TextColumn::make('day')
                //                    ->getStateUsing(function(TourDayExpense $record) {
                //                        return $record->tourDay->date->format('d.m.Y');
                //                    }),
                Tables\Columns\TextColumn::make('type')
                    ->formatStateUsing(function (TourDayExpense $record) {
                        return $record->type->getLabel();
                    }),
                Tables\Columns\TextColumn::make('name')
                    ->getStateUsing(function (TourDayExpense $record) {
                        return match ($record->type) {
                            ExpenseType::Hotel => $record->hotel?->name,
                            ExpenseType::Guide => $record->guides->map(fn ($guide) => $guide->name)->join(', '),
                            ExpenseType::Transport => $record->transport_place,
                            ExpenseType::Lunch,
                            ExpenseType::Dinner => $record->restaurant?->name,
                            ExpenseType::Train => $record->train?->name,
                            ExpenseType::Flight => $record->plane_route,
                            ExpenseType::Show => $record->show?->name,
                            default => $record->other_name,
                        };
                    }),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(function (TourDayExpense $record) {
                        $symbol = $record->price_currency?->getSymbol();
                        if (! $symbol) {
                            $symbol = ExpenseService::getMainCurrency()?->from?->getSymbol();
                        }

                        return TourService::formatMoney($record->price).' '.$symbol;
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('comment')
                    ->width('250px')
                    ->limit(50)
                    ->wrap()
                    ->tooltip(fn ($record) => $record->comment),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalWidth('5xl')
                    ->after(function ($record) {
                        /** @var Tour $tour */
                        $tour = $this->getOwnerRecord();
                        $this->ensureFollowingDaysForHotelExpense($tour, $record);
                        $tour->saveExpensesTotal();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth('5xl')
                    ->after(function ($record) {
                        /** @var Tour $tour */
                        $tour = $this->getOwnerRecord();
                        $this->ensureFollowingDaysForHotelExpense($tour, $record);
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

    /**
     * When a Hotel expense spans more than one night, auto-creates the
     * following TourDay rows (same city, no expenses) so the operator
     * doesn't have to add each night's day by hand. Skips any date that
     * already has a day.
     */
    protected function ensureFollowingDaysForHotelExpense(Tour $tour, ?TourDayExpense $record): void
    {
        if (! $record || $record->type != ExpenseType::Hotel) {
            return;
        }

        $nights = (float) ($record->hotel_total_nights ?? 0);
        $checkinDate = $record->tourDay?->date;
        if ($nights > 1 && $checkinDate) {
            TourService::ensureFollowingDaysExist(
                $tour,
                Carbon::parse($checkinDate),
                $nights,
                $record->tourDay?->city_id
            );
        }
    }
}
