<?php

namespace App\Filament\Resources\TourTpsTestResource\RelationManagers;

use Filament\Tables;
use Filament\Forms\Form;
use App\Enums\GuideType;
use App\Enums\ExpenseType;
use Filament\Tables\Table;
use App\Enums\ExpenseStatus;
use App\Services\TourService;
use App\Models\TourDayExpense;
use Filament\Forms\Components;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Filament\Resources\RelationManagers\RelationManager;

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
                        ->options(function($get) {
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
                                ->required()
                                ->label('Status'),

                            self::getExpensePriceInput('Guide price'),

                            Components\Textarea::make('comment')->label('Comment'),
                        ]),
                    ]),
                ])->visible(fn($get) => $get('type') == ExpenseType::Guide->value),

                // Transport
                Components\Fieldset::make('Transport info')->schema([

                    Components\Grid::make(4)->schema([
                        Components\Select::make('transport_driver_ids')
                            ->label('Drivers')
                            ->multiple()
                            ->options(TourService::getDrivers())
                            ->native(false)
                            ->searchable()
                            ->preload(),
                        Components\TimePicker::make('transport_time')
                            ->seconds(false),
                        Components\TextInput::make('transport_place')
                            ->label('Pickup location'),
                        Components\TextInput::make('transport_route')
                            ->label('Route'),
                    ]),

                    Components\Grid::make(4)->schema([
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

                        self::getExpensePriceInput('Sell price'),

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
                            ->createOptionAction(function() {
                                return [
                                    'url' => route('museum.create'),
                                    'label' => 'Create museum',
                                ];
                            })
                            ->suffixAction(function() {
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
                            ->disabled(function($get) {
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

                // Plane
                Components\Fieldset::make('Plane info')->schema([

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

                        Components\TimePicker::make('arrival_time')
                            ->seconds(false)
                            ->label('Arrival time'),

                        Components\Textarea::make('comment')
                            ->label('Comment'),
                    ]),

                ])->visible(fn($get) => $get('type') == ExpenseType::Plane->value),

                // Extra
                Components\Fieldset::make('Extra info')->schema([
                    Components\Grid::make(3)->schema([
                        Components\TextInput::make('other_name')
                            ->label('Name'),

                        self::getExpensePriceInput(),

                        Components\Textarea::make('comment')->label('Comment'),
                    ]),
                ])->visible(fn($get) => $get('type') == ExpenseType::Extra->value),

            ]);
    }

    public static function isLunch($expenseType): bool
    {
        return in_array($expenseType, [ExpenseType::Lunch->value, ExpenseType::Dinner->value]);
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
                        $set('price_currency', $get('price_currency') == 'USD' ? 'UZS' : 'USD');
                    })
            )
            ->numeric();
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->recordTitleAttribute('tourDay.date')
            ->defaultGroup(
                Tables\Grouping\Group::make('tourDay.date')
                    ->label('Day')
                    ->getTitleFromRecordUsing(function(TourDayExpense $record) {
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
                    ->formatStateUsing(function(TourDayExpense $record) {
                        return $record->type->getLabel();
                    }),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('price'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
