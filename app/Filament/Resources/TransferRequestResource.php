<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\TransferRequest;
use Filament\Resources\Resource;
use App\Enums\TransportClassEnum;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\TransferRequestResource\Pages;

class TransferRequestResource extends Resource
{
    protected static ?string $model = TransferRequest::class;
    
    protected static ?string $label = 'Transfer Requests';
    protected static ?string $pluralLabel = 'Transfer Requests';
    
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Website Management';
    protected static ?int $navigationSort = 4;
    
    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('from_city_id')
                    ->label('From City')
                    ->relationship('fromCity', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                
                Forms\Components\Select::make('to_city_id')
                    ->label('To City')
                    ->relationship('toCity', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                
                Forms\Components\DateTimePicker::make('date_time')
                    ->label('Date & Time')
                    ->required(),
                
                Forms\Components\TextInput::make('passengers_count')
                    ->label('Passengers Count')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(50)
                    ->required(),
                
                Forms\Components\Select::make('transport_class')
                    ->label('Transport Class')
                    ->options(TransportClassEnum::class)
                    ->nullable(),
                
                Forms\Components\TextInput::make('fio')
                    ->label('Full Name')
                    ->maxLength(255)
                    ->required(),
                
                Forms\Components\TextInput::make('phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(255)
                    ->required(),
                
                Forms\Components\Textarea::make('comment')
                    ->label('Comment')
                    ->rows(3)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                
                Forms\Components\TextInput::make('payment_type')
                    ->label('Payment Type')
                    ->maxLength(255),
                
                Forms\Components\TextInput::make('payment_card')
                    ->label('Payment Card')
                    ->maxLength(255),
                
                Forms\Components\TextInput::make('payment_holder_name')
                    ->label('Card Holder Name')
                    ->maxLength(255),
                
                Forms\Components\TextInput::make('payment_valid_until')
                    ->label('Card Valid Until')
                    ->maxLength(255),
                
                Forms\Components\Checkbox::make('is_sample_baggage')
                    ->label('Is Sample Baggage'),
                
                Forms\Components\TextInput::make('baggage_count')
                    ->label('Baggage Count')
                    ->numeric()
                    ->minValue(0),
                
                Forms\Components\TextInput::make('terminal_name')
                    ->label('Terminal Name')
                    ->maxLength(255),
                
                Forms\Components\TextInput::make('text_on_sign')
                    ->label('Text on Sign')
                    ->maxLength(255),
                
                Forms\Components\Checkbox::make('activate_flight_tracking')
                    ->label('Activate Flight Tracking'),
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('fromCity.name')
                    ->label('From City')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('toCity.name')
                    ->label('To City')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('date_time')
                    ->label('Date & Time')
                    ->dateTime()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('passengers_count')
                    ->label('Passengers')
                    ->numeric()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('fio')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('terminal_name')
                    ->label('Terminal Name')
                    ->searchable()
                    ->placeholder('Not specified'),
                
                Tables\Columns\TextColumn::make('baggage_count')
                    ->label('Baggage Count')
                    ->numeric()
                    ->placeholder('Not specified'),
                
                Tables\Columns\IconColumn::make('is_sample_baggage')
                    ->label('Sample Baggage')
                    ->boolean(),
                
                Tables\Columns\IconColumn::make('activate_flight_tracking')
                    ->label('Flight Tracking')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('text_on_sign')
                    ->label('Text on Sign')
                    ->searchable()
                    ->placeholder('Not specified')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('transport_class')
                    ->label('Transport Class')
                    ->formatStateUsing(fn(?TransportClassEnum $state): string => $state?->getLabel() ?? 'Not specified')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('from_city_id')
                    ->label('From City')
                    ->relationship('fromCity', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('to_city_id')
                    ->label('To City')
                    ->relationship('toCity', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until_date')
                            ->label('Until Date'),
                    ])
                    ->query(function(Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date_time', '>=', $date),
                            )
                            ->when(
                                $data['until_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date_time', '<=', $date),
                            );
                    }),
                
                Tables\Filters\SelectFilter::make('transport_class')
                    ->label('Transport Class')
                    ->options(TransportClassEnum::class),
                
                Tables\Filters\SelectFilter::make('payment_type')
                    ->label('Payment Type')
                    ->options(function() {
                        return TransferRequest::whereNotNull('payment_type')
                            ->distinct()
                            ->pluck('payment_type', 'payment_type')
                            ->toArray();
                    }),
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
            'index' => Pages\ListTransferRequests::route('/'),
            'edit' => Pages\EditTransferRequest::route('/{record}/edit'),
        ];
    }
}