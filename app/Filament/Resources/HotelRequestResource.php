<?php

namespace App\Filament\Resources;

use App\Enums\WebTourStatus;
use App\Filament\Resources\HotelRequestResource\Pages;
use App\Filament\Resources\HotelRequestResource\RelationManagers;
use App\Models\HotelRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HotelRequestResource extends Resource
{
    protected static ?string $model = HotelRequest::class;
    
    protected static ?string $label = 'Hotel Requests';
    protected static ?string $pluralLabel = 'Hotel Requests';
    
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Website Management';
    protected static ?int $navigationSort = 4;
    
    public static function canViewAny(): bool
    {
        return !auth()->user()->isOperator() && !auth()->user()->isAccountant();
    }
    
    public static function getNavigationBadge(): ?string
    {
        $count = static::$model::where('status', WebTourStatus::New->value)->count();
        return $count > 0 ? (string)$count : null;
    }
    
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->disabled(fn() => auth()->user()->isOperator())
            ->schema([
                Forms\Components\Select::make('hotel_id')
                    ->label('Hotel')
                    ->relationship('hotel', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                    
                Forms\Components\Select::make('room_type_id')
                    ->label('Room Type')
                    ->relationship('roomType', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                    
                Forms\Components\DateTimePicker::make('checkin_time')
                    ->label('Check-in Date & Time')
                    ->required(),
                    
                Forms\Components\DateTimePicker::make('checkout_time')
                    ->label('Check-out Date & Time')
                    ->after('checkin_time')
                    ->required(),
                    
                Forms\Components\Select::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                    
                Forms\Components\Textarea::make('comment')
                    ->label('Comments')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('hotel.name')
                    ->label('Hotel')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('roomType.name')
                    ->label('Room Type')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('checkin_time')
                    ->label('Check-in')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('checkout_time')
                    ->label('Check-out')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Guest'),
                    
                Tables\Columns\TextColumn::make('comment')
                    ->label('Comments')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('status_updated_by')
                    ->formatStateUsing(function($record) {
                        return $record->statusUpdatedBy?->name;
                    }),
                
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
                Tables\Filters\SelectFilter::make('hotel_id')
                    ->label('Hotel')
                    ->relationship('hotel', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('room_type_id')
                    ->label('Room Type')
                    ->relationship('roomType', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHotelRequests::route('/'),
            'edit' => Pages\EditHotelRequest::route('/{record}/edit'),
        ];
    }
}
