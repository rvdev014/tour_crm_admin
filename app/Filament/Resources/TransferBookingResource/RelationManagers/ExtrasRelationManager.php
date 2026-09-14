<?php

namespace App\Filament\Resources\TransferBookingResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ExtrasRelationManager extends RelationManager
{
    protected static string $relationship = 'extras';

    // Read-only: quantities/prices are snapshotted at booking time by
    // TransferQuoteService::createBooking() and shouldn't be edited here.
    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('pivot.quantity')
                    ->label(__('Quantity')),
                Tables\Columns\TextColumn::make('pivot.unit_price')
                    ->label(__('Unit price'))
                    ->money('USD'),
                Tables\Columns\TextColumn::make('pivot.total_price')
                    ->label(__('Total'))
                    ->money('USD'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
