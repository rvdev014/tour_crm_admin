<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use App\Models\RoomType;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Filament\Resources\RoomTypeResource\Pages;
use App\Filament\Resources\RoomTypeResource\RelationManagers;

class RoomTypeResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return !auth()->user()->isOperator() && !auth()->user()->isAccountant();
    }
    
    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
    
    public static function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('hotels')
                    ->multiple()
                    ->relationship('hotels', 'name')
                    ->preload()
                    ->columnSpanFull()
                    ->saveRelationshipsUsing(function (RoomType $record, $state) {
                        $record->hotels()->syncWithPivotValues($state, ['price' => 0]);
                    }),
                Forms\Components\FileUpload::make('picture')
                    ->label('Picture')
                    ->image()
                    ->disk('public')
                    ->directory('room-types')
                    ->imageEditor()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('picture')
                    ->label('Picture')
                    ->disk('public')
                    ->size(60),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
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
            'index' => Pages\ListRoomTypes::route('/'),
            'create' => Pages\CreateRoomType::route('/create'),
            'edit' => Pages\EditRoomType::route('/{record}/edit'),
        ];
    }
}
