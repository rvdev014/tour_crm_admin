<?php

namespace App\Filament\Resources\TrainResource\RelationManagers;

use App\Services\TourService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TariffsRelationManager extends RelationManager
{
    protected static string $relationship = 'tariffs';

    public function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
            ->schema([
                Forms\Components\Select::make('from_city_id')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->label(__('City from'))
                    ->relationship('fromCity', 'name')
                    ->options(fn() => TourService::getCities(null, isAll: true))
                    ->reactive(),

                Forms\Components\Select::make('to_city_id')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->label(__('City to'))
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

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('class_second')
                        ->label(__('Class Second'))
                        ->numeric(),

                    Forms\Components\TextInput::make('class_business')
                        ->label(__('Class Business'))
                        ->numeric(),

                    Forms\Components\TextInput::make('class_vip')
                        ->label(__('Class VIP'))
                        ->numeric(),
                ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('train_id')
            ->columns([
                Tables\Columns\TextColumn::make('fromCity.name')
                    ->label(__('City from'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('toCity.name')
                    ->label(__('City to'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('class_second')
                    ->numeric()
                    ->label(__('Class Second'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('class_business')
                    ->numeric()
                    ->label(__('Class Business'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('class_vip')
                    ->numeric()
                    ->label(__('Class VIP'))
                    ->searchable(),
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
