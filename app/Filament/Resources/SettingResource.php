<?php

namespace App\Filament\Resources;

use Filament\Tables;
use App\Models\Setting;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Enums\DefaultSettings;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Filament\Resources\SettingResource\Pages;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static ?string $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 99;
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('key')
                    ->options([
                        DefaultSettings::TOUR_SBOR->value => DefaultSettings::TOUR_SBOR->getLabel(),
                    ])
                    ->disabled()
                    ->required(),
                TextInput::make('value')->required(),
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->formatStateUsing(fn (string $state): string => DefaultSettings::tryFrom($state)?->getLabel() ?? $state)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSettings::route('/'),
        ];
    }
}

