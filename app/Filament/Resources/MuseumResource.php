<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MuseumResource\Pages;
use App\Filament\Resources\MuseumResource\RelationManagers;
use App\Filament\Resources\MuseumResource\RelationManagers\ChildrenRelationManager;
use App\Models\Museum;
use App\Services\TourService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MuseumResource extends Resource
{
    protected static ?string $model = Museum::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationGroup = 'Manual';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'inn', 'contract'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'INN' => $record->inn,
            'Price per Person' => $record->price_per_person,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('inn')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('legal_name')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('contract')
                        ->maxLength(255),
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
                        ->options(fn($get) => TourService::getCities($get('country_id')))
                        ->preload()
                        ->reactive(),
                    Forms\Components\TextInput::make('price_per_person')
                        ->required()
                        ->numeric(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->striped()
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('inn')
                    ->searchable(),
                Tables\Columns\TextColumn::make('legal_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contract')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_per_person')
                    ->numeric()
                    ->sortable(),
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
            ChildrenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMuseums::route('/'),
            'create' => Pages\CreateMuseum::route('/create'),
            'edit' => Pages\EditMuseum::route('/{record}/edit'),
        ];
    }
}
