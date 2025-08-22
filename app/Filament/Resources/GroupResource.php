<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GroupResource\Pages;
use App\Filament\Resources\GroupResource\RelationManagers;
use App\Models\Group;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Repeater::make('groupItems')
                    ->relationship()
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('from_price')
                            ->required()
                            ->numeric()
                            ->step(1),
                        Forms\Components\TextInput::make('to_price')
                            ->required()
                            ->numeric()
                            ->step(1),
                        Forms\Components\TextInput::make('percent')
                            ->required()
                            ->numeric()
                            ->step(1)
                            ->suffix('%'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['from_price'] && $state['to_price'] ? "{$state['from_price']} - {$state['to_price']} ({$state['percent']}%)" : null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('groupItems_count')
                    ->counts('groupItems')
                    ->label('Price Ranges'),
                Tables\Columns\TextColumn::make('companies_count')
                    ->counts('companies')
                    ->label('Companies'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroups::route('/'),
        ];
    }
}
