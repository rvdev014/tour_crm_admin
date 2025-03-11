<?php

namespace App\Filament\Resources;

use App\Enums\RoomSeasonType;
use App\Filament\Resources\HotelResource\Pages;
use App\Filament\Resources\HotelResource\RelationManagers\RoomTypesRelationManager;
use App\Models\Hotel;
use App\Services\TourService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HotelResource extends Resource
{
    protected static ?string $model = Hotel::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationGroup = 'Manual';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('inn'),
                ]),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('country_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->relationship('country', 'name')
                        ->afterStateUpdated(fn($get, $set) => $set('city_id', null))
                        ->reactive(),

                    Forms\Components\Select::make('city_id')
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->relationship('city', 'name')
                        ->options(fn($get) => TourService::getCities($get('country_id'))),

                    Forms\Components\TextInput::make('booking_cancellation_days')->numeric(),
                ]),

                Forms\Components\Repeater::make('periods')
                    ->relationship('periods')
                    ->columnSpanFull()
                    ->addActionAlignment('end')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('start_date')
                                ->required(),
                            Forms\Components\DatePicker::make('end_date')
                                ->minDate(fn($get) => $get('start_date'))
                                ->required(),
                            Forms\Components\Select::make('season_type')
                                ->options(function() {
                                    return [
                                        RoomSeasonType::Seasonal->value => RoomSeasonType::Seasonal->getLabel(),
                                        RoomSeasonType::OffSeasonal->value => RoomSeasonType::OffSeasonal->getLabel(),
                                    ];
                                }),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('inn'),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('City')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking_cancellation_days')
                    ->label('Booking days')
                    ->sortable(),
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
            RoomTypesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHotels::route('/'),
            'create' => Pages\CreateHotel::route('/create'),
            'edit' => Pages\EditHotel::route('/{record}/edit'),
        ];
    }
}
