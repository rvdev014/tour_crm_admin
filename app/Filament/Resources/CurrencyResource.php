<?php

namespace App\Filament\Resources;

use App\Enums\CurrencyEnum;
use App\Filament\Resources\CurrencyResource\Pages;
use App\Filament\Resources\CurrencyResource\RelationManagers;
use App\Models\Currency;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?int $navigationSort = 14;
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('from')
                    ->native(false)
                    ->options(CurrencyEnum::class)
                    ->required(),
                Forms\Components\Select::make('to')
                    ->native(false)
                    ->options(CurrencyEnum::class)
                    ->required(),
                Forms\Components\TextInput::make('rate')
                    ->required()
                    ->numeric(),
                Forms\Components\Checkbox::make('is_main')
                    ->rules([
                        fn(Get $get): Closure => function (string $attribute, $value, $fail) use ($get) {
                            if (!$get('is_main')) {
                                return;
                            }

                            $exists = Currency::query()
                                ->where('is_main', true)
                                ->when($get('id'), function ($query) use ($get) {
                                    $query->where('id', '!=', $get('id'));
                                })
                                ->exists();
                            if ($exists) {
                                $fail('The main currency already exists.');
                            }
                        },
                    ])
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->columns([
                Tables\Columns\TextColumn::make('from')
                    ->searchable(),
                Tables\Columns\TextColumn::make('to')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rate')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_main')
                    ->boolean(),
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
            'index' => Pages\ManageCurrencies::route('/'),
        ];
    }
}
