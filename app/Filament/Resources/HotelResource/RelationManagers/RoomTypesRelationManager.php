<?php

namespace App\Filament\Resources\HotelResource\RelationManagers;

use Closure;
use Filament\Forms;
use Filament\Tables;
use App\Models\Hotel;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\HotelPeriod;
use App\Models\HotelRoomType;
use Filament\Resources\RelationManagers\RelationManager;

class RoomTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'roomTypes';
    
    public function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
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
                                    ->when(
                                        $get('id'),
                                        fn($query) => $query->whereNot('id', $get('id'))
                                    )
                                    ->where('room_type_id', $value)
                                    ->where('season_type', $get('season_type'))
                                    ->exists()
                            ) {
                                $fail('The selected room type is already associated with the hotel.');
                            }
                        },
                    ]),
                Forms\Components\Select::make('hotel_period_id')
                    ->label('Hotel Period')
                    ->options(fn(Forms\Get $get) => HotelPeriod::query()
                        ->where('hotel_id', $this->getOwnerRecord()->id)
                        ->get()
                        ->pluck('extended_label', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('price')
                    ->label('Price Uz')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('price_foreign')
                    ->label('Price Foreign')
                    ->required()
                    ->maxLength(255),
            ]);
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->striped()
            ->recordTitleAttribute('roomType.name')
            ->columns([
                Tables\Columns\TextColumn::make('roomType.name'),
                Tables\Columns\TextColumn::make('period.season_type')
                    ->label('Period')
                    ->badge() // Превращает текст в цветной бадж
                    // Цвет и иконка подтянутся из Enum автоматически,
                    // так как колонка ссылается на поле с типом RoomSeasonType
                    ->formatStateUsing(function($state, $record) {
                        /** @var HotelRoomType $record */
                        return $record->period?->getExtendedLabelAttribute() ?? '-';
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price Uz')
                    ->money()
                    ->numeric(),
                Tables\Columns\TextColumn::make('price_foreign')
                    ->label('Price Foreign')
                    ->money()
                    ->numeric(),
                //                Tables\Columns\TextColumn::make('person_type')->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->authorize(fn() => auth()->user()->isAdmin())
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
                ]),
            ]);
    }
}
