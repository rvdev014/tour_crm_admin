<?php

namespace App\Filament\Resources\HotelResource\RelationManagers;

use Carbon\Carbon;
use App\Models\HotelPeriod;
use App\Enums\RoomPersonType;
use App\Enums\RoomSeasonType;
use App\Models\Hotel;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use App\Models\HotelRoomType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

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
                Forms\Components\Select::make('season_type')
                    ->required()
                    ->options(RoomSeasonType::class),
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
                Tables\Columns\TextColumn::make('season_type')->badge(),
                Tables\Columns\TextColumn::make('period_ranges')
                    ->getStateUsing(function($record) {
                        /** @var HotelRoomType $record */
                        $requiredSeasonType = $record->season_type;

                        $periods = $record->hotel->periods()
                            ->where('season_type', $requiredSeasonType)
                            ->get(['start_date', 'end_date']);

                        if ($periods->isEmpty()) {
                            return '';
                        }

                        $ranges = $periods->map(function ($period) {
                            $start = Carbon::parse($period->start_date)->format('d.m');
                            $end = Carbon::parse($period->end_date)->format('d.m');
                            return "{$start} - {$end}";
                        });

                        return $ranges->implode(', ');
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
