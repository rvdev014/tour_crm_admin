<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Filament\Resources\TourCorporateResource\Pages;
use App\Filament\Resources\TourCorporateResource\RelationManagers;
use App\Models\City;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\Tour;
use App\Services\TourService;
use Closure;
use Filament\Forms\Components;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class TourCorporateResource_old extends Resource
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
            Components\Select::make('company_id')
                ->native(false)
                ->relationship('company', 'name')
                ->required()
                ->afterStateUpdated(function($get, $set) {
                    $hotels = $get('hotels') ?? [];
                    $updatedHotels = collect($hotels)->map(function ($hotel) use ($get) {
                        $additionalPercent = TourService::getAdditionalPercent($get('company_id'));
                        $price = TourService::getHotelPrice($hotel['hotel_room_type_id'], $additionalPercent);
                        $hotel['additional_percent'] = $additionalPercent;
                        $hotel['price'] = $price;
                        return $hotel;
                    })->toArray();

                    $set('hotels', $updatedHotels);
                })
                ->reactive(),
            Components\DatePicker::make('start_date')
                ->required(),
            Components\DatePicker::make('end_date')
                ->required(),
            Components\Select::make('country_id')
                ->native(false)
                ->relationship('country', 'name')
                ->afterStateUpdated(fn($get, $set) => $set('city_id', null))
                ->reactive()
                ->required(),
            Components\Select::make('city_id')
                ->native(false)
                ->relationship('city', 'name')
                ->preload()
                ->options(fn ($get) => TourService::getCities($get('country_id'))),
            Components\TextInput::make('pax')
                ->required()
                ->numeric(),
            Components\TextInput::make('price')
                ->required()
                ->numeric(),

            Components\Repeater::make('hotels')
                ->extraAttributes(['class' => 'repeater-hotels repeater-days'])
                ->collapsible()
                ->relationship('hotels')
                ->addActionLabel('Add hotel')
                ->columnSpanFull()
                ->addActionAlignment('end')
                ->itemLabel(function ($get, $uuid) {
                    $current = Arr::get($get('hotels'), $uuid);
                    $index = array_search($uuid, array_keys($get('hotels'))) ?? 0;
                    $index++;

                    if ($current['hotel_id']) {
                        $hotel = Hotel::find($current['hotel_id']);
                        return "Hotel {$hotel->name} ($index)";
                    }

                    return "Hotel $index";
                })
                ->schema([
                    Components\Grid::make()->schema([
                        Components\Select::make('hotel_id')
                            ->label('Hotel')
                            ->relationship('hotel', 'name')
                            ->required()
                            ->afterStateUpdated(function($get, $set) {
                                $set('hotel_room_type_id', null);
                                $set('additional_percent', null);
                                $set('price', null);
                            })
                            ->reactive(),

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
                            ->preload()
                            ->afterStateUpdated(function($get, $set) {
                                $additionalPercent = TourService::getAdditionalPercent($get('../../company_id'));
                                $price = TourService::getHotelPrice($get('hotel_room_type_id'), $additionalPercent);
                                $set('additional_percent', $additionalPercent);
                                $set('price', $price);
                            })
                            ->required()
                            ->reactive()
                    ]),

                    Components\Grid::make()->schema([
                        Components\Select::make('status')
                            ->options(ExpenseStatus::class)
                            ->required(),
                        Components\TextInput::make('pax'),
                    ]),

                    Components\Grid::make()->schema([
                        Components\TextInput::make('additional_percent')
                            ->label('Additional Percent (%)')
                            ->formatStateUsing(fn($record) => $record?->additional_percent)
                            ->readOnly(),
                        Components\TextInput::make('price')->required(),
                    ]),
                ])
        ]);
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
                    ->formatStateUsing(function($record, $state) {
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
                    ->formatStateUsing(function($record, $state) {
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
                    ->formatStateUsing(function($record, $state) {
                        if (TourService::isVisible($record)) {
                            return TourService::formatMoney($state);
                        }

                        return '-';
                    })
                    ->sortable(),
                Columns\TextColumn::make('createdBy.name')->sortable(),
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
