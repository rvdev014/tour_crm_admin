<?php

namespace App\Filament\Resources;

use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use App\Filament\Resources\TransportResource\Pages;
use App\Filament\Resources\TransportResource\RelationManagers;
use App\Models\Transport;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransportResource extends Resource
{
    protected static ?string $model = Transport::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?int $navigationSort = 8;
    protected static ?string $navigationGroup = 'Manual';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->options(TransportType::class)
                    ->required()
                    ->rules([
                        fn(Get $get): Closure => function(string $attribute, $value, $fail) use ($get) {
                            $exists = Transport::query()
                                ->where('type', $get('type'))
                                ->where('comfort_level', $get('comfort_level'))
                                ->exists();
                            if ($exists) {
                                $fail('The combination of type and comfort level already exists.');
                            }
                        },
                    ]),
                Forms\Components\Select::make('comfort_level')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->options(TransportComfortLevel::class)
                    ->required(),
                Forms\Components\TextInput::make('price')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('comfort_level')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
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
            'index' => Pages\ListTransports::route('/'),
            'create' => Pages\CreateTransport::route('/create'),
            'edit' => Pages\EditTransport::route('/{record}/edit'),
        ];
    }
}
