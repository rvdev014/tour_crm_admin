<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecommendedHotelResource\Pages;
use App\Filament\Resources\RecommendedHotelResource\RelationManagers;
use App\Models\Hotel;
use App\Models\RecommendedHotel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RecommendedHotelResource extends Resource
{
    protected static ?string $model = RecommendedHotel::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationGroup = 'Website Management';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('hotel_id')
                    ->relationship('hotel', 'name')
                    ->options(function () {
                        return Hotel::query()
                            ->whereNotIn('id', RecommendedHotel::query()->pluck('hotel_id'))
                            ->pluck('name', 'id');
                    })
                    ->required(),
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->columns([
                Tables\Columns\TextColumn::make('hotel.name')
                    ->label('Hotel Name'),
                Tables\Columns\TextColumn::make('hotel.city.name')
                    ->label('City'),
                Tables\Columns\TextColumn::make('hotel.country.name')
                    ->label('Country'),
            ])
            ->filters([
                //
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRecommendedHotels::route('/'),
        ];
    }
}
