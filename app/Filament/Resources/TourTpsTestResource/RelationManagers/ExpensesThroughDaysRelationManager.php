<?php

namespace App\Filament\Resources\TourTpsTestResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Models\TourDayExpense;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExpensesThroughDaysRelationManager extends RelationManager
{
    protected static string $relationship = 'expensesThroughDays';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('type')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type,status')
            ->defaultGroup(
                Tables\Grouping\Group::make('tourDay.date')
                    ->label('Day')
                    ->getTitleFromRecordUsing(function(TourDayExpense $record) {
                        return $record->tourDay->date->format('d.m.Y');
                    })
                    ->collapsible(),
            )
            ->columns([

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
