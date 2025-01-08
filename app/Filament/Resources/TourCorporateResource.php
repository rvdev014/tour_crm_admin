<?php

namespace App\Filament\Resources;

use App\Enums\CompanyType;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use App\Filament\Resources\TourCorporateResource\Pages;
use App\Filament\Resources\TourCorporateResource\RelationManagers;
use App\Models\Museum;
use App\Models\Tour;
use App\Services\TourService;
use Filament\Forms\Components;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class TourCorporateResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Tours Corporate';
    protected static ?string $slug = 'tour-corporate';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', TourType::Corporate->value);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Components\Fieldset::make('Tour details')->schema([

                Components\TextInput::make('group_number')
                    ->formatStateUsing(function ($record) {
                        if (!empty($record)) {
                            return $record->group_number;
                        }
                        return TourService::getGroupNumber(TourType::Corporate);
                    })
                    ->readOnly(),
                Components\Select::make('company_id')
                    ->native(false)
                    ->relationship('company', 'name')
                    ->options(fn($get) => TourService::getCompanies(CompanyType::Corporate))
                    ->afterStateUpdated(function ($get, $set) {
                        $days = collect($get('days') ?? []);

                        $updatedDays = $days->map(function ($day) use ($get) {
                            $expenses = collect($day['expenses'] ?? []);
                            $updatedExpenses = $expenses->map(function ($expense) use ($get) {
                                if ($expense['type'] == ExpenseType::Hotel->value) {
                                    $additionalPercent = TourService::getAdditionalPercent($get('company_id'));
                                    $price = TourService::getHotelPrice(
                                        $expense['hotel_room_type_id'],
                                        $additionalPercent
                                    );
                                    $expense['hotel_add_percent'] = $additionalPercent;
                                    $expense['price'] = $price;
                                    return $expense;
                                }

                                return $expense;
                            });

                            $day['expenses'] = $updatedExpenses->toArray();
                            return $day;
                        });

                        $set('days', $updatedDays->toArray());
                    })
                    ->reactive()
                    ->required(),

                Components\Repeater::make('passengers')
                    ->relationship('passengers')
                    ->addActionLabel('Add passenger')
                    ->minItems(1)
                    ->simple(
                        Components\TextInput::make('name')
                            ->label('Passenger name')
                            ->required(),
                    ),

                Components\Textarea::make('comment'),
            ]),

//            Components\DatePicker::make('start_date')
//                ->required(),
//            Components\DatePicker::make('end_date')
//                ->required(),
//            Components\Select::make('country_id')
//                ->native(false)
//                ->relationship('country', 'name')
//                ->afterStateUpdated(fn($get, $set) => $set('city_id', null))
//                ->reactive()
//                ->required(),
//            Components\Select::make('city_id')
//                ->native(false)
//                ->relationship('city', 'name')
//                ->options(fn($get) => TourService::getCities($get('country_id')))
//                ->preload()
//                ->reactive(),
//            Components\TextInput::make('pax')
//                ->required()
//                ->numeric(),
//            Components\TextInput::make('price')
//                ->required()
//                ->numeric(),

            Components\Repeater::make('days')
                ->extraAttributes(['class' => 'repeater-days'])
                ->collapsible()
                ->relationship('days')
                ->addActionLabel('Add day')
                ->columnSpanFull()
                ->addActionAlignment('end')
                ->itemLabel(function ($get, $uuid) {
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
                    Components\Grid::make()->schema([
                        Components\DatePicker::make('date')
                            ->displayFormat('d.m.Y')
                            ->required()
                            ->reactive(),
                        Components\Select::make('city_id')
                            ->native(false)
                            ->relationship('city', 'name')
                            ->options(fn($get) => TourService::getCities($get('../../country_id')))
                            ->preload()
                            ->reactive()
                            ->required(),
                    ]),
                    Components\Repeater::make('expenses')
                        ->extraAttributes(['class' => 'repeater-expenses'])
                        ->collapsible()
                        ->itemLabel(function ($get, $uuid) {
                            $current = Arr::get($get('expenses'), $uuid);
                            $index = array_search($uuid, array_keys($get('expenses'))) ?? 0;
                            $index++;

                            $expenseType = $current['type'];
                            if ($expenseType) {
                                $expenseTypeLabel = ExpenseType::from($expenseType)->getLabel();
                                return "Expense for $expenseTypeLabel ($index)";
                            }

                            return "Expense $index";
                        })
                        ->relationship('expenses')
                        ->addActionLabel('Add expense')
                        ->addActionAlignment('end')
                        ->schema([
                            Components\Grid::make()->schema([
                                Hidden::make('index'),
                                Components\Select::make('type')
                                    ->native(false)
                                    ->label('Expense Type')
                                    ->options(ExpenseType::class)
                                    ->required()
                                    ->reactive(),
                            ]),

                            // Hotel
                            Components\Fieldset::make('Hotel info')->schema([
                                Components\Grid::make()->schema([
                                    Components\Select::make('hotel_id')
                                        ->native(false)
                                        ->label('Hotel')
                                        ->relationship('hotel', 'name')
                                        ->options(fn ($get) => TourService::getHotels($get('../../city_id')))
                                        ->preload()
                                        ->reactive(),
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->native(false)
                                        ->label('Status'),
                                    Components\Textarea::make('comment')
                                        ->label('Comment')
                                        ->columnSpanFull(),
                                ])
                            ])->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),

                            // Guide
                            Components\Fieldset::make('Guide info')->schema([
                                Components\Grid::make()->schema([
                                    Components\TextInput::make('guide_name')
                                        ->label('Guide name'),
                                    Components\TextInput::make('guide_phone')
                                        ->label('Guide phone'),

                                    Components\Select::make('status')
                                        ->native(false)
                                        ->options(ExpenseStatus::class)
                                        ->label('Status'),

                                    Components\TextInput::make('guide_price')
                                        ->statePath('price')
                                        ->label('Price'),

                                    Components\Textarea::make('comment')
                                        ->label('Comment')
                                        ->columnSpanFull(),
                                ]),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Guide->value),

                            // Transport
                            Components\Fieldset::make('Transport info')->schema([

                                Components\Select::make('transport_type')
                                    ->native(false)
                                    ->label('Transport type')
                                    ->options(TransportType::class)
                                    ->afterStateUpdated(function ($get, $set) {
                                        $price = TourService::getTransportPrice(
                                            $get('transport_type'),
                                            $get('transport_comfort_level'),
                                        );
                                        $set('price', $price);
                                    })
                                    ->reactive(),

                                Components\Select::make('to_city_id')
                                    ->native(false)
                                    ->label('City to')
                                    ->relationship('toCity', 'name')
                                    ->options(TourService::getCities())
                                    ->preload()
                                    ->reactive()
                                    ->preload(),

                                Components\Select::make('transport_comfort_level')
                                    ->native(false)
                                    ->label('Comfort level')
                                    ->options(TransportComfortLevel::class)
                                    ->afterStateUpdated(function ($get, $set) {
                                        $price = TourService::getTransportPrice(
                                            $get('transport_type'),
                                            $get('transport_comfort_level'),
                                        );
                                        $set('price', $price);
                                    })
                                    ->reactive(),

                                Components\Select::make('status')
                                    ->native(false)
                                    ->options(ExpenseStatus::class)
                                    ->label('Status'),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                            // Museum
                            Components\Fieldset::make('Museum info')->schema([

                                Components\Select::make('museum_id')
                                    ->label('Museum')
                                    ->native(false)
                                    ->relationship('museum', 'name')
                                    ->options(fn ($get) => TourService::getMuseums($get('../../city_id')))
                                    ->createOptionForm([
                                        Components\Grid::make()->schema([
                                            Components\TextInput::make('name')
                                                ->required()
                                                ->maxLength(255),
                                            Components\TextInput::make('inn')
                                                ->required()
                                                ->maxLength(255),
                                            Components\Select::make('country_id')
                                                ->native(false)
                                                ->relationship('country', 'name')
                                                ->afterStateUpdated(fn($get, $set) => $set('city_id', null))
                                                ->reactive(),
                                            Components\Select::make('city_id')
                                                ->native(false)
                                                ->relationship('city', 'name')
                                                ->options(fn($get) => TourService::getCities($get('country_id')))
                                                ->preload()
                                                ->reactive(),
                                            Components\TextInput::make('price_per_person')
                                                ->required(),
                                        ])
                                    ])
                                    ->preload()
                                    ->reactive(),
                                Components\Select::make('museum_item_id')
                                    ->label('Museum Children')
                                    ->native(false)
                                    ->relationship('museumItem', 'name')
                                    ->reactive()
                                    ->disabled(function ($get) {
                                        if (!$get('museum_id')) {
                                            return true;
                                        }
                                        /** @var Museum $museum */
                                        $museum = Museum::find($get('museum_id'));
                                        return !$museum || $museum->children->count() == 0;
                                    }),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Museum->value),

                            // Lunch and Dinner
                            Components\Fieldset::make('Lunch / Dinner info')->schema([

                                Components\Select::make('restaurant_id')
                                    ->native(false)
                                    ->label('Restaurant')
                                    ->relationship('restaurant', 'name')
                                    ->options(fn ($get) => TourService::getRestaurants($get('../../city_id')))
                                    ->reactive()
                                    ->preload(),

                                Components\Select::make('status')
                                    ->options(ExpenseStatus::class)
                                    ->native(false)
                                    ->label('Status'),
                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => self::isLunch($get('type'))),

                            // Train
                            Components\Fieldset::make('Train info')->schema([

                                Components\Grid::make(3)->schema([
                                    Components\Select::make('train_name')
                                        ->native(false)
                                        ->label('Train name')
                                        ->options([
                                            'sharq' => 'Sharq',
                                            'afrosiyob' => 'Afrosiyob',
                                        ]),

                                    Components\Select::make('to_city_id')
                                        ->native(false)
                                        ->label('City to')
                                        ->relationship('toCity', 'name')
                                        ->options(TourService::getCities())
                                        ->reactive()
                                        ->preload(),

                                    Components\TextInput::make('price')
                                        ->label('Price')
                                        ->required(),
                                ]),

                                Components\Grid::make(3)->schema([
                                    Components\TextInput::make('train_class_economy')
                                        ->label('Economy')
                                        ->numeric(),
                                    Components\TextInput::make('train_class_vip')
                                        ->label('VIP')
                                        ->numeric(),
                                    Components\TextInput::make('train_class_second')
                                        ->label('Second')
                                        ->numeric(),
                                ]),

                                Components\Grid::make(3)->schema([
                                    Components\TimePicker::make('departure_time')
                                        ->label('Departure time'),
                                    Components\TimePicker::make('arrival_time')
                                        ->label('Arrival time'),
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->native(false)
                                        ->label('Status'),
                                ]),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Train->value),

                            // Show
                            Components\Fieldset::make('Show info')->schema([

                                Components\Grid::make()->schema([
                                    Components\Select::make('show_id')
                                        ->native(false)
                                        ->label('Show')
                                        ->relationship('show', 'name')
                                        ->options(fn ($get) => TourService::getShows($get('../../city_id')))
                                        ->reactive()
                                        ->preload()
                                        ->required(),
                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->native(false)
                                        ->label('Status'),
                                    Components\Textarea::make('comment')
                                        ->label('Comment')
                                        ->columnSpanFull(),
                                ]),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Show->value),

                            // Plane
                            Components\Fieldset::make('Plane info')->schema([

                                Components\TextInput::make('price')
                                    ->label('Price')
                                    ->required(),

                                Components\Select::make('to_city_id')
                                    ->native(false)
                                    ->label('City to')
                                    ->relationship('toCity', 'name')
                                    ->options(TourService::getCities())
                                    ->reactive()
                                    ->preload(),

                                Components\Grid::make(3)->schema([
                                    Components\TimePicker::make('departure_time')->label('Departure time'),

                                    Components\TimePicker::make('arrival_time')->label('Arrival time'),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->native(false)
                                        ->label('Status'),

                                ]),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Plane->value),

                            // Other
                            Components\Fieldset::make('Plane info')->schema([
                                Components\TextInput::make('other_name')
                                    ->label('Name'),

                                Components\TextInput::make('price')
                                    ->label('Price')
                                    ->required(),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Other->value),

                            // Conference
                            Components\Fieldset::make('Conference info')->schema([

                                Components\TextInput::make('conference_name')
                                    ->label('Conference name'),

                                Components\TextInput::make('price')
                                    ->label('Price')
                                    ->required(),

                                Components\TextInput::make('coffee_break')
                                    ->suffix('%')
                                    ->label('Coffee break'),

                                Components\Select::make('status')
                                    ->options(ExpenseStatus::class)
                                    ->native(false)
                                    ->label('Status'),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Conference->value),
                        ])
                ])
        ]);
    }

    public static function isLunch($expenseType): bool
    {
        return in_array($expenseType, [ExpenseType::Lunch->value, ExpenseType::Dinner->value]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
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
                Columns\TextColumn::make('price')
                    ->formatStateUsing(function ($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state);
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('expenses')
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
                Columns\TextColumn::make('createdBy.name')->sortable(),
                Columns\TextColumn::make('createdBy.operator_percent_tps')
                    ->label('Operator %')
                    ->suffix('%')
                    ->sortable(),
                Columns\TextColumn::make('country.name'),
            ])
            ->filters([
                //
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
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'edit' => Pages\EditTour::route('/{record}/edit'),
        ];
    }
}
