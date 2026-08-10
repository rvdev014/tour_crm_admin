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
use App\Exceptions\DatabaseErrorTranslator;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Filament\Resources\TransferRequestResource\Pages;

class TransferRequestResource extends Resource
{

    // Sidebar label — Filament otherwise falls back to the auto-derived
    // English plural model name (e.g. "Hotels"), which never changes with
    // the panel's locale. See AppServiceProvider for the equivalent
    // ->translateLabel() hook covering field/column labels; this can't be
    // done the same way since getNavigationLabel() is called statically.
    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }

    // See the comment on getNavigationLabel() above / AdminPanelProvider's
    // navigationGroups() — Filament matches resources to their registered
    // group by comparing this value against the group's getLabel(), so both
    // sides need translating the same way or the match silently fails.
    public static function getNavigationGroup(): ?string
    {
        return ($group = parent::getNavigationGroup()) ? __($group) : null;
    }

    // Breadcrumb text ("X > List" above the page heading) — a third, separate
    // label pipeline from getNavigationLabel()/getNavigationGroup() above
    // (falls back to getTitleCasePluralModelLabel(), not either of those).
    public static function getBreadcrumb(): string
    {
        return __(parent::getBreadcrumb());
    }

    // Plural model label — feeds table empty states ("Не найдено tours") and
    // some page headings. Singular getModelLabel() is deliberately NOT
    // overridden; see the class-level comment above the other nav overrides.
    public static function getPluralModelLabel(): string
    {
        return __(parent::getPluralModelLabel());
    }
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
                    ->label(__('From'))
                    ->required(),
                
                Forms\Components\Select::make('to')
                    ->label(__('To'))
                    ->required(),
                
                Forms\Components\DateTimePicker::make('date_time')
                    ->label(__('Date & Time'))
                    ->required(),
                
                Forms\Components\TextInput::make('passengers_count')
                    ->label(__('Passengers Count'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(50)
                    ->required(),
                
                Forms\Components\Select::make('transport_class_id')
                    ->label(__('Transport Class'))
                    ->relationship('transportClass', 'name')
                    ->nullable(),
                
                Forms\Components\TextInput::make('fio')
                    ->label(__('Full Name'))
                    ->maxLength(255)
                    ->required(),
                
                Forms\Components\TextInput::make('phone')
                    ->label(__('Phone'))
                    ->tel()
                    ->maxLength(255)
                    ->required(),
                
                Forms\Components\Textarea::make('comment')
                    ->label(__('Comment'))
                    ->rows(3)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                
                Forms\Components\Checkbox::make('is_sample_baggage')
                    ->label(__('Is Sample Baggage')),
                
                Forms\Components\TextInput::make('baggage_count')
                    ->label(__('Baggage Count'))
                    ->numeric()
                    ->minValue(0),
                
                Forms\Components\TextInput::make('terminal_name')
                    ->label(__('Terminal Name'))
                    ->maxLength(255),
                
                Forms\Components\TextInput::make('text_on_sign')
                    ->label(__('Text on Sign'))
                    ->maxLength(255),
                
                Forms\Components\Checkbox::make('activate_flight_tracking')
                    ->label(__('Activate Flight Tracking')),
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
                    ->label(__('ID'))
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('from')
                    ->label(__('From'))
                    ->wrap()
                    ->extraAttributes(['class' => 'w-[200px]'])
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('to')
                    ->label(__('To'))
                    ->wrap()
                    ->extraAttributes(['class' => 'w-[200px]'])
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('distance')
                    ->label(__('Distance'))
                    ->suffix(' km')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('transportClass.name')
                    ->label(__('Transport Class'))
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('date_time')
                    ->label(__('Date & Time'))
                    ->dateTime()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('passengers_count')
                    ->label(__('Passengers'))
                    ->numeric()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('fio')
                    ->label(__('Full Name'))
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('status_updated_by')
                    ->formatStateUsing(function($record) {
                        return $record->statusUpdatedBy?->name;
                    }),
                
                Tables\Columns\TextColumn::make('terminal_name')
                    ->label(__('Location details'))
                    ->searchable()
                    ->placeholder(__('Not specified')),
                
                Tables\Columns\TextColumn::make('baggage_count')
                    ->label(__('Baggage Count'))
                    ->numeric()
                    ->placeholder(__('Not specified')),
                
                //                Tables\Columns\IconColumn::make('is_sample_baggage')
                //                    ->label('Sample Baggage')
                //                    ->boolean(),
                
                //                Tables\Columns\IconColumn::make('activate_flight_tracking')
                //                    ->label('Flight Tracking')
                //                    ->boolean(),
                
                Tables\Columns\TextColumn::make('text_on_sign')
                    ->label(__('Text on Sign'))
                    ->searchable()
                    ->placeholder(__('Not specified'))
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('until_date')
                            ->label(__('Until Date')),
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
                    ->label(__('Transport Class'))
                    ->relationship('transportClass', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('accept')
                    ->label(__('Accept'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(
                        fn(TransferRequest $record
                        ) => $record->status === TransferRequestStatus::Booked && $record->status !== TransferRequestStatus::Accepted
                    )
                    ->requiresConfirmation()
                    ->modalHeading(__('Accept Transfer Request'))
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
                            $errorId = (string) Str::uuid();
                            Log::error("[{$errorId}] Failed to accept transfer request #{$record->id}: {$exception->getMessage()}", [
                                'exception' => $exception,
                            ]);

                            $message = $exception instanceof QueryException
                                ? DatabaseErrorTranslator::translate($exception)['message']
                                : 'Please try again or contact support.';

                            Notification::make()
                                ->title('Could not accept request')
                                ->body("{$message} (Error ID: {$errorId})")
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
