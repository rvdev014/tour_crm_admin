<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseStatus;
use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use App\Filament\Resources\TransferResource\Pages;
use App\Filament\Resources\TransferResource\RelationManagers;
use App\Models\Transfer;
use App\Services\TourService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('from_city_id')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->label('City from')
                    ->relationship('fromCity', 'name')
                    ->options(fn() => TourService::getCities(null, isAll: true))
                    ->reactive(),

                Forms\Components\Select::make('to_city_id')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->label('City to')
                    ->relationship('toCity', 'name')
                    ->options(function ($get) {
                        $fromCityId = $get('from_city_id');
                        if (!empty($fromCityId)) {
                            $cities = TourService::getCities(null, false, true);
                            return $cities->filter(fn($city) => $city->id != $fromCityId)->pluck('name', 'id');
                        }

                        return [];
                    })
                    ->preload()
                    ->reactive(),

                Forms\Components\Select::make('company_id')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->required(),

                Forms\Components\TextInput::make('group_number')
                    ->label('Group number'),

                Forms\Components\Select::make('transport_type')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->label('Transport type')
                    ->options(TransportType::class)
                    ->reactive()
                    ->afterStateUpdated(function ($get, $set) {
                        $price = TourService::getTransportPrice(
                            $get('transport_type'),
                            $get('transport_comfort_level'),
                        );
                        $set('price', $price);
                    }),

                Forms\Components\Select::make('transport_comfort_level')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->label('Comfort level')
                    ->options(TransportComfortLevel::class)
                    ->reactive()
                    ->afterStateUpdated(function ($get, $set) {
                        $price = TourService::getTransportPrice(
                            $get('transport_type'),
                            $get('transport_comfort_level'),
                        );
                        $set('price', $price);
                    }),

                Forms\Components\TextInput::make('pax')
                    ->label('Pax'),

                Forms\Components\Select::make('status')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->options(ExpenseStatus::class)
                    ->label('Status'),

                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric(),

                Forms\Components\Textarea::make('comment')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('company.name'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('place_of_submission')->sortable(),
                Tables\Columns\TextColumn::make('pax')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fromCity.name')
                    ->label('Route')
                    ->formatStateUsing(function ($record, $state) {
                        return $state . ' - ' . $record->toCity?->name;
                    }),
//                Tables\Columns\TextColumn::make('toCity.name')->sortable(),
                Tables\Columns\TextColumn::make('driver'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transport_type')->sortable(),
                Tables\Columns\TextColumn::make('transport_comfort_level')->sortable(),
                Tables\Columns\TextColumn::make('group_number')->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
//                Tables\Columns\TextColumn::make('updated_at')
//                    ->dateTime()
//                    ->sortable()
//                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListTransfers::route('/'),
            'create' => Pages\CreateTransfer::route('/create'),
            'edit' => Pages\EditTransfer::route('/{record}/edit'),
        ];
    }
}
