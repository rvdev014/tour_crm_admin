<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use App\Models\Restaurant;
use Filament\Tables\Table;
use App\Services\TourService;
use Filament\Resources\Resource;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use App\Filament\Resources\RestaurantResource\Pages;
use App\Filament\Resources\RestaurantResource\RelationManagers;

class RestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?int $navigationSort = 7;
    protected static ?string $navigationGroup = 'Manual';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required(),
                    Forms\Components\TextInput::make('inn')
                        ->required(),
                    Forms\Components\TextInput::make('company_name')
                        ->label('Legal name'),
                ]),
                Forms\Components\Grid::make(4)->schema([
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

                    Forms\Components\TextInput::make('price_per_person')
                        ->required()
                        ->numeric(),
                    PhoneInput::make('phone')
                        ->strictMode()
                        ->onlyCountries(['UZ'])
                        ->defaultCountry('UZ')
                        ->suffixAction(function($record) {
                            if (!$record?->phone) {
                                return [];
                            }
                            return [
                                Forms\Components\Actions\Action::make('create_museum')
                                    ->icon('heroicon-o-paper-airplane')
                                    ->url("https://t.me/{$record->phone}", true)
                            ];
                        }),
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
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Legal name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('country.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->url(fn($record) => $record->phone ? "https://t.me/{$record->phone}" : null, true)
                    ->color('info')
                    ->html(),
                Tables\Columns\TextColumn::make('price_per_person'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListRestaurants::route('/'),
            'create' => Pages\CreateRestaurant::route('/create'),
            'edit' => Pages\EditRestaurant::route('/{record}/edit'),
        ];
    }
}
