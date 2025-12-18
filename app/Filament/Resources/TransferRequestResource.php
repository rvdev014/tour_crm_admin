<?php

namespace App\Filament\Resources;

use Throwable;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\TransferRequest;
use Filament\Resources\Resource;
use App\Services\TransferService;
use App\Enums\TransferRequestStatus;
use Filament\Notifications\Notification;
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
    
    
    public static function canViewAny(): bool
    {
        return !auth()->user()->isOperator() && !auth()->user()->isAccountant();
    }
    
    public static function getNavigationBadge(): ?string
    {
        $count = static::$model::where('status', TransferRequestStatus::Created->value)->count();
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
                Forms\Components\TextInput::make('from')
                    ->label('From')
                    ->required(),
                
                Forms\Components\Select::make('to')
                    ->label('To')
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
                
                Forms\Components\Select::make('transport_class_id')
                    ->label('Transport Class')
                    ->relationship('transportClass', 'name')
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
            ->defaultSort('status', 'asc')
            ->paginationPageOptions([30, 50, 100])
            ->defaultPaginationPageOption(30)
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('from')
                    ->label('From')
                    ->wrap()
                    ->extraAttributes(['style' => 'width: 200px'])
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('to')
                    ->label('To')
                    ->wrap()
                    ->extraAttributes(['style' => 'width: 200px'])
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('distance')
                    ->label('Distance')
                    ->suffix(' km')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('transportClass.name')
                    ->label('Transport Class')
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
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('status_updated_by')
                    ->formatStateUsing(function($record) {
                        return $record->statusUpdatedBy?->name;
                    }),
                
                Tables\Columns\TextColumn::make('terminal_name')
                    ->label('Location details')
                    ->searchable()
                    ->placeholder('Not specified'),
                
                Tables\Columns\TextColumn::make('baggage_count')
                    ->label('Baggage Count')
                    ->numeric()
                    ->placeholder('Not specified'),
                
                //                Tables\Columns\IconColumn::make('is_sample_baggage')
                //                    ->label('Sample Baggage')
                //                    ->boolean(),
                
                //                Tables\Columns\IconColumn::make('activate_flight_tracking')
                //                    ->label('Flight Tracking')
                //                    ->boolean(),
                
                Tables\Columns\TextColumn::make('text_on_sign')
                    ->label('Text on Sign')
                    ->searchable()
                    ->placeholder('Not specified')
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
                
                Tables\Filters\SelectFilter::make('transport_class_id')
                    ->label('Transport Class')
                    ->relationship('transportClass', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('accept')
                    ->label('Accept')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(
                        fn(TransferRequest $record
                        ) => $record->status === TransferRequestStatus::Booked && $record->status !== TransferRequestStatus::Accepted
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Accept Transfer Request')
                    ->modalDescription('This will create a new transfer and send a confirmation email to the user.')
                    ->action(function(TransferRequest $record) {
                        try {
                            $transfer = TransferService::acceptRequest($record);
                            Notification::make()
                                ->title('Transfer request accepted')
                                ->body("Transfer #{$transfer->number} has been created and confirmation email sent.")
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('Error occurred')
                                ->body("Error accepting request: {$exception->getMessage()}")
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->authorize(fn() => auth()->user()->isAdmin())
            ], position: Tables\Enums\ActionsPosition::AfterColumns)
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
            'index' => Pages\ListTransferRequests::route('/'),
            'edit' => Pages\EditTransferRequest::route('/{record}/edit'),
        ];
    }
}
