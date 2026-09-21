<?php

namespace App\Filament\Resources\TransferResource\RelationManagers;

use App\Enums\DriverExpenseStatus;
use App\Models\TransferDriverExpense;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * What the driver (or a dispatcher for them) spent on this trip, with the receipt photo, for an operator
 * to approve or reject.
 *
 * Reviewing is a record of "somebody checked this" only: nothing here changes a transfer price, a tour
 * total or any profit figure. Entries are made from the driver cabinet, never here.
 */
class DriverExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'driverExpenses';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Driver expenses');
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
            ->modifyQueryUsing(fn ($query) => $query->with(['addedBy:id,name,role', 'reviewedBy:id,name']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('When'))
                    // Real instants stored in the app timezone (UTC); staff and drivers work in Tashkent.
                    ->dateTime('d.m.Y H:i', 'Asia/Tashkent'),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, TransferDriverExpense $record) => $record->formattedAmount()),
                // The photo is on a private disk: this URL is an authenticated route, not a file path.
                Tables\Columns\ImageColumn::make('receipt')
                    ->label(__('Receipt'))
                    ->height(56)
                    ->getStateUsing(fn (TransferDriverExpense $record) => route('admin.driver-expenses.receipt', $record))
                    ->url(fn (TransferDriverExpense $record) => route('admin.driver-expenses.receipt', $record), shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('addedBy.name')
                    ->label(__('Added by'))
                    ->placeholder('—')
                    ->description(fn (TransferDriverExpense $record) => $record->addedBy?->isDispatcher() ? __('Dispatcher') : null),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                Tables\Columns\TextColumn::make('reviewedBy.name')
                    ->label(__('Reviewed by'))
                    ->placeholder('—')
                    ->description(fn (TransferDriverExpense $record) => $record->review_note),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label(__('Approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TransferDriverExpense $record) => $record->status === DriverExpenseStatus::New)
                    ->requiresConfirmation()
                    ->action(function (TransferDriverExpense $record) {
                        $record->update([
                            'status' => DriverExpenseStatus::Approved,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'review_note' => null,
                        ]);

                        Notification::make()->title(__('Expense approved'))->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label(__('Reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TransferDriverExpense $record) => $record->status === DriverExpenseStatus::New)
                    // The reason is shown to whoever entered it, so they can see what to fix.
                    ->form([
                        Forms\Components\Textarea::make('review_note')
                            ->label(__('Reason'))
                            ->maxLength(500),
                    ])
                    ->action(function (TransferDriverExpense $record, array $data) {
                        $record->update([
                            'status' => DriverExpenseStatus::Rejected,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'review_note' => filled($data['review_note'] ?? null) ? $data['review_note'] : null,
                        ]);

                        Notification::make()->title(__('Expense rejected'))->success()->send();
                    }),

                // Undo a mis-click. Also unlocks the entry again for whoever entered it.
                Tables\Actions\Action::make('reopen')
                    ->label(__('Reopen review'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (TransferDriverExpense $record) => $record->status !== DriverExpenseStatus::New && auth()->user()->isAdmin())
                    ->requiresConfirmation()
                    ->action(function (TransferDriverExpense $record) {
                        $record->update([
                            'status' => DriverExpenseStatus::New,
                            'reviewed_by' => null,
                            'reviewed_at' => null,
                            'review_note' => null,
                        ]);

                        Notification::make()->title(__('Review reopened'))->success()->send();
                    }),

                Tables\Actions\Action::make('delete_expense')
                    ->label(__('Delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn () => auth()->user()->isAdmin())
                    ->requiresConfirmation()
                    ->modalDescription(__('The receipt photo will be deleted too.'))
                    ->action(fn (TransferDriverExpense $record) => $record->delete()),   // removes the photo too
            ]);
    }
}
