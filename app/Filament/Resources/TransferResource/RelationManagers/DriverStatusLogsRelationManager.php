<?php

namespace App\Filament\Resources\TransferResource\RelationManagers;

use App\Models\TransferDriverStatusLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Who moved this transfer through which driver status, and when. Read-only: history is appended by
 * DriverTransferStatusService, never edited by hand.
 */
class DriverStatusLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'driverStatusLogs';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Driver status history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->striped()
            ->recordTitleAttribute('id')
            ->defaultSort('id', 'desc')
            ->paginated(false)
            ->columns([
                // Log timestamps are real instants (stored in the app timezone, UTC); the operators
                // and drivers live in Tashkent, the same zone TourService::notifyDrivers() assumes.
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('When'))
                    ->dateTime('d.m.Y H:i:s', 'Asia/Tashkent'),
                Tables\Columns\TextColumn::make('from_status')
                    ->label(__('From'))
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('to_status')
                    ->label(__('To'))
                    ->badge(),
                Tables\Columns\TextColumn::make('source')
                    ->label(__('Changed by'))
                    ->formatStateUsing(fn (string $state, TransferDriverStatusLog $record) => match ($state) {
                        TransferDriverStatusLog::SOURCE_DRIVER => $record->driver?->name ?? __('Driver'),
                        TransferDriverStatusLog::SOURCE_DISPATCHER => __('Dispatcher').': '.($record->driver?->name ?? '—'),
                        TransferDriverStatusLog::SOURCE_ADMIN => __('Operator'),
                        default => __('System'),
                    }),
            ]);
    }
}
