<?php

namespace App\Filament\Resources\HotelResource\RelationManagers;

use App\Enums\RoomPersonType;
use App\Enums\RoomSeasonType;
use App\Models\Hotel;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RoomTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'roomTypes';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('room_type_id')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->relationship('roomType', 'name')
                    ->required()
                    ->rules([
                        fn(Get $get): Closure => function(string $attribute, $value, $fail) use ($get) {
                            /** @var Hotel $hotel */
                            $hotel = $this->getOwnerRecord();
                            if (
                                $hotel->roomTypes()
                                    ->where('room_type_id', $value)
                                    ->where('season_type', $get('season_type'))
                                    ->where('person_type', $get('person_type'))
                                    ->exists()
                            ) {
                                $fail('The selected room type is already associated with the hotel.');
                            }
                        },
                    ]),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('season_type')
                    ->required()
                    ->options(RoomSeasonType::class),
                Forms\Components\Select::make('person_type')
                    ->required()
                    ->options(RoomPersonType::class),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->striped()
            ->recordTitleAttribute('roomType.name')
            ->columns([
                Tables\Columns\TextColumn::make('roomType.name'),
                Tables\Columns\TextColumn::make('season_type')->badge(),
                Tables\Columns\TextColumn::make('person_type')->badge(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->numeric(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
