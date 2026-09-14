<?php

namespace App\Filament\Resources;

use App\Enums\TransferBookingStatus;
use App\Exceptions\DatabaseErrorTranslator;
use App\Filament\Resources\TransferBookingResource\Pages;
use App\Filament\Resources\TransferBookingResource\RelationManagers;
use App\Models\TransferBooking;
use App\Services\TransferQuoteService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TransferBookingResource extends Resource
{
    // See TransportClassResource for why these four overrides exist —
    // Filament's nav label / nav group / breadcrumb / plural-label are
    // separate pipelines that each need their own __() wrap.
    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }

    public static function getNavigationGroup(): ?string
    {
        return ($group = parent::getNavigationGroup()) ? __($group) : null;
    }

    public static function getBreadcrumb(): string
    {
        return __(parent::getBreadcrumb());
    }

    public static function getPluralModelLabel(): string
    {
        return __(parent::getPluralModelLabel());
    }

    protected static ?string $model = TransferBooking::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Website Management';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return ! auth()->user()->isOperator() && ! auth()->user()->isAccountant();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::$model::where('status', TransferBookingStatus::New->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->disabled(fn () => auth()->user()->isOperator())
            ->schema([
                Forms\Components\Section::make(__('Customer'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('first_name')->required(),
                        Forms\Components\TextInput::make('last_name')->required(),
                        Forms\Components\TextInput::make('email')->email()->required(),
                        Forms\Components\TextInput::make('phone')->tel()->required(),
                        Forms\Components\TextInput::make('hotel_address')
                            ->label(__('Address or hotel'))
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('flight_data')
                            ->label(__('Flight data')),
                        Forms\Components\Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make(__('Totals'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('transfers_total')->numeric()->prefix('$')->disabled(),
                        Forms\Components\TextInput::make('extras_total')->numeric()->prefix('$')->disabled(),
                        Forms\Components\TextInput::make('total')->numeric()->prefix('$')->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('Customer'))
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_round_trip')
                    ->label(__('Return'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('total')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(TransferBookingStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('accept')
                    ->label(__('Accept'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TransferBooking $record) => $record->status === TransferBookingStatus::New)
                    ->requiresConfirmation()
                    ->modalHeading(__('Accept Transfer Booking'))
                    ->modalDescription(__('This will create a transfer for every vehicle in this booking and send a confirmation email to the customer.'))
                    ->action(function (TransferBooking $record) {
                        try {
                            $transfers = app(TransferQuoteService::class)->acceptBooking($record);

                            Notification::make()
                                ->title(__('Booking accepted'))
                                ->body(__(':count transfer(s) created for booking :reference.', [
                                    'count' => $transfers->count(),
                                    'reference' => $record->reference,
                                ]))
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            $errorId = (string) Str::uuid();
                            Log::error("[{$errorId}] Failed to accept transfer booking #{$record->id}: {$exception->getMessage()}", [
                                'exception' => $exception,
                            ]);

                            $message = $exception instanceof QueryException
                                ? DatabaseErrorTranslator::translate($exception)['message']
                                : 'Please try again or contact support.';

                            Notification::make()
                                ->title(__('Could not accept booking'))
                                ->body("{$message} (Error ID: {$errorId})")
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['legs.transportClass']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LegsRelationManager::class,
            RelationManagers\ExtrasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransferBookings::route('/'),
            'edit' => Pages\EditTransferBooking::route('/{record}/edit'),
        ];
    }
}
