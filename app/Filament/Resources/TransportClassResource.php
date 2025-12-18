<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransportClassResource\Pages;
use App\Filament\Resources\TransportClassResource\RelationManagers;
use App\Models\TransportClass;
use App\Models\TransportType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransportClassResource extends Resource
{
    protected static ?string $model = TransportClass::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Website Management';
    protected static ?int $navigationSort = 6;
    
    public static function canViewAny(): bool
    {
        return !auth()->user()->isOperator() && !auth()->user()->isAccountant();
    }
    
    public static function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->rows(3),
                Forms\Components\TextInput::make('price_per_km')
                    ->required()
                    ->numeric()
                    ->step(0.01)
                    ->prefix('$'),
                Forms\Components\FileUpload::make('photo')
                    ->image()
                    ->directory('transport-classes'),

                Forms\Components\TextInput::make('limit_distance')
                    ->numeric()
                    ->label('Limit distance'),
                Forms\Components\TextInput::make('additional_price_per_km')
                    ->numeric()
                    ->label('Additional price per km'),

                Forms\Components\TextInput::make('passenger_capacity')
                    ->numeric()
                    ->label('Passenger Capacity'),
                Forms\Components\TextInput::make('luggage_capacity')
                    ->numeric()
                    ->label('Luggage Capacity'),

                Forms\Components\TextInput::make('waiting_time_included')
                    ->numeric()
                    ->label('Waiting Time Included'),
                Forms\Components\Checkbox::make('meeting_with_place')
                    ->label('Meeting with Place'),
                Forms\Components\Checkbox::make('non_refundable_rate')
                    ->label('Non Refundable Rate'),
                Forms\Components\TextInput::make('vehicle_example')
                    ->maxLength(255)
                    ->label('Vehicle Example'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
                Tables\Columns\TextColumn::make('price_per_km')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('limit_distance')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('additional_price_per_km')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('photo')
                    ->circular(),
                Tables\Columns\TextColumn::make('passenger_capacity')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('luggage_capacity')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('waiting_time_included')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('meeting_with_place')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('non_refundable_rate')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vehicle_example')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Actions\DeleteAction::make()->authorize(fn() => auth()->user()->isAdmin())
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->authorize(fn() => auth()->user()->isAdmin()),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTransportClasses::route('/'),
        ];
    }
}
