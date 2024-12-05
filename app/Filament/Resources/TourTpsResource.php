<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\GuideType;
use App\Enums\TourType;
use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use App\Filament\Resources\TourTpsResource\Pages;
use App\Filament\Resources\TourTpsResource\RelationManagers;
use App\Models\City;
use App\Models\HotelRoomType;
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

class TourTpsResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Tours TPS';
    protected static ?string $slug = 'tour-tps';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', TourType::TPS->value);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Components\Select::make('company_id')
                ->relationship('company', 'name')
                ->reactive()
                ->required(),
            Components\DatePicker::make('start_date')
                ->required(),
            Components\DatePicker::make('end_date')
                ->required(),
            Components\Select::make('country_id')
                ->relationship('country', 'name')
                ->afterStateUpdated(fn($get, $set) => $set('city_id', null))
                ->reactive()
                ->required(),
            Components\Select::make('city_id')
                ->relationship('city', 'name')
                ->options(function ($get) {
                    $countryId = $get('country_id');
                    if (!empty($countryId)) {
                        return City::where('country_id', $countryId)->get()->pluck('name', 'id');
                    }

                    return [];
                }),
            Components\TextInput::make('pax')
                ->required()
                ->numeric(),
            Components\TextInput::make('price')
                ->required()
                ->numeric(),

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
                            ->required()
                            ->reactive(),
                        Components\Select::make('city_id')
                            ->relationship('city', 'name')
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
                                    ->label('Expense Type')
                                    ->options(ExpenseType::class)
                                    ->required()
                                    ->reactive(),

                                Components\TextInput::make('price')
                                    ->label('Price')
                                    ->required(),

                                Components\Textarea::make('comment')
                                    ->label('Comment')
                                    ->columnSpanFull(),
                            ]),

                            // Hotel
                            Components\Grid::make()->schema([
                                Components\Select::make('hotel_id')
                                    ->label('Hotel')
                                    ->relationship('hotel', 'name')
                                    ->reactive()
                                    ->afterStateUpdated(fn($set) => $set('hotel_room_type_id', null))
                                    ->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),
                                Components\Select::make('hotel_room_type_id')
                                    ->label('Hotel Room Type')
                                    ->options(function ($get) {
                                        $hotelId = $get('hotel_id');
                                        if (!empty($hotelId)) {
                                            $result = [];
                                            $hRoomTypes = HotelRoomType::where('hotel_id', $hotelId)->get();
                                            foreach ($hRoomTypes as $hRoomType) {
                                                $result[$hRoomType->id] = "{$hRoomType->roomType->name} {$hRoomType->price}";
                                            }

                                            return $result;
                                        }

                                        return [];
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($get, $set) {
                                        $hotelRoomType = HotelRoomType::find($get('hotel_room_type_id'));
                                        $set('price', $hotelRoomType->price);
                                    })
                                    ->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),

                                Components\Select::make('status')
                                    ->options(ExpenseStatus::class)
                                    ->label('Status')
                                    ->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Hotel->value),

                            // Ticket
                            Components\Grid::make(3)->schema([

                                Components\Grid::make()->schema([
                                    Components\Select::make('museum_id')
                                        ->label('Museum')
                                        ->relationship('museum', 'name')
                                        ->reactive()
                                        ->afterStateUpdated(function ($get, $set) {
                                            $set('museum_item_id', null);

                                            $price = TourService::getMuseumPrice(
                                                $get('museum_id'),
                                                $get('pax'),
                                                $get('museum_item_id')
                                            );
                                            $set('price', $price);
                                        })
                                        ->visible(fn($get) => $get('type') == ExpenseType::Ticket->value),
                                    Components\Select::make('museum_item_id')
                                        ->label('Museum Children')
                                        ->relationship('museumItem', 'name')
                                        ->reactive()
                                        ->afterStateUpdated(function ($get, $set) {
                                            $price = TourService::getMuseumPrice(
                                                $get('museum_id'),
                                                $get('pax'),
                                                $get('museum_item_id')
                                            );
                                            $set('price', $price);
                                        })
                                        ->visible(function ($get) {
                                            if (!$get('museum_id')) {
                                                return false;
                                            }
                                            /** @var Museum $museum */
                                            $museum = Museum::find($get('museum_id'));
                                            return $museum && $museum->children->count() > 0;
                                        }),
                                ]),

                                Components\Grid::make()->schema([
                                    Components\TextInput::make('museum_inn')
                                        ->label('Museum Inn')
                                        ->visible(fn($get) => $get('type') == ExpenseType::Ticket->value),

                                    Components\TextInput::make('pax')
                                        ->label('Pax')
                                        ->numeric()
                                        ->reactive()
                                        ->afterStateUpdated(function ($get, $set) {
                                            $price = TourService::getMuseumPrice(
                                                $get('museum_id'),
                                                $get('pax'),
                                                $get('museum_item_id')
                                            );
                                            $set('price', $price);
                                        })
                                        ->visible(fn($get) => $get('type') == ExpenseType::Ticket->value),
                                ]),

                                Components\Grid::make()->schema([
                                    Components\TextInput::make('museum_guide')
                                        ->label('Guide')
                                        ->visible(fn($get) => $get('type') == ExpenseType::Ticket->value),

                                    Components\Select::make('status')
                                        ->options(ExpenseStatus::class)
                                        ->label('Status')
                                        ->visible(fn($get) => $get('type') == ExpenseType::Ticket->value),
                                ]),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Ticket->value),

                            // Guide
                            Components\Grid::make()->schema([
                                Components\TextInput::make('guide_name')
                                    ->label('Guide')
                                    ->visible(fn($get) => $get('type') == ExpenseType::Guide->value),

                                Components\Select::make('guide_type')
                                    ->label('Guide type')
                                    ->options(GuideType::class)
                                    ->visible(fn($get) => $get('type') == ExpenseType::Guide->value),

                                Components\TextInput::make('pax')
                                    ->label('Pax')
                                    ->visible(fn($get) => $get('type') == ExpenseType::Guide->value),

                                Components\Select::make('status')
                                    ->options(ExpenseStatus::class)
                                    ->label('Status')
                                    ->visible(fn($get) => $get('type') == ExpenseType::Guide->value),
                            ]),

                            // Transport
                            Components\Grid::make()->schema([
                                Components\Select::make('transport_type')
                                    ->label('Transport type')
                                    ->options(TransportType::class)
                                    ->reactive()
                                    ->afterStateUpdated(function ($get, $set) {
                                        $price = TourService::getTransportPrice(
                                            $get('transport_type'),
                                            $get('transport_comfort_level'),
                                        );
                                        $set('price', $price);
                                    })
                                    ->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                                Components\Select::make('transport_comfort_level')
                                    ->label('Comfort level')
                                    ->options(TransportComfortLevel::class)
                                    ->reactive()
                                    ->afterStateUpdated(function ($get, $set) {
                                        $price = TourService::getTransportPrice(
                                            $get('transport_type'),
                                            $get('transport_comfort_level'),
                                        );
                                        $set('price', $price);
                                    })
                                    ->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                            Components\Grid::make()->schema([
                                Components\TextInput::make('pax')
                                    ->label('Pax')
                                    ->visible(fn($get) => $get('type') == ExpenseType::Transport->value),

                                Components\Select::make('status')
                                    ->options(ExpenseStatus::class)
                                    ->label('Status')
                                    ->visible(fn($get) => $get('type') == ExpenseType::Transport->value),
                            ])->visible(fn($get) => $get('type') == ExpenseType::Transport->value),


                            // Lunch and Dinner
                            Components\Grid::make()->schema([
                                Components\Select::make('restaurant_id')
                                    ->label('Restaurant')
                                    ->relationship('restaurant', 'name')
                                    ->reactive()
                                    ->visible(fn($get) => self::isLunchAndDinner($get('type'))),

                                Components\TextInput::make('pax')
                                    ->label('Pax')
                                    ->visible(fn($get) => self::isLunchAndDinner($get('type'))),

                            ])->visible(fn($get) => self::isLunchAndDinner($get('type'))),

                            // Other
                            Components\Grid::make()->schema([

                                Components\TextInput::make('other_name')
                                    ->label('Name')
                                    ->visible(fn($get) => $get('type') == ExpenseType::Other->value),

                            ])->visible(fn($get) => $get('type') == ExpenseType::Other->value),

                        ])
                ])
        ]);
    }

    public static function isLunchAndDinner($expenseType): bool
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
                    ->money()
                    ->sortable(),
//                    ->visible(fn($record) => TourService::visiblePrice($record)),
                Columns\TextColumn::make('expenses')
                    ->money()
                    ->badge()
                    ->color('danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->sortable(),
//                    ->visible(fn($record) => TourService::visiblePrice($record)),
                Columns\TextColumn::make('income')
                    ->money()
                    ->badge()
                    ->color(fn(Tour $record) => $record->income > 0 ? 'success' : 'danger')
                    ->size(Columns\TextColumn\TextColumnSize::Large)
                    ->sortable(),
//                    ->visible(fn($record) => TourService::visiblePrice($record)),
                Columns\TextColumn::make('createdBy.name')
                    ->sortable(),

                /*Columns\TextColumn::make('status')
                    ->numeric()
                    ->sortable(),
                Columns\TextColumn::make('pax')
                    ->numeric()
                    ->sortable(),*/
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
