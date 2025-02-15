<?php

namespace App\Filament\Resources\DriverResource\Pages;

use App\Filament\Resources\DriverResource;
use App\Models\Driver;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class EditDriver extends EditRecord
{
    protected static string $resource = DriverResource::class;


    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


    protected function getHeaderActions(): array
    {
        return [
//            Actions\DeleteAction::make(),
            Actions\Action::make('delete')
                ->modalIcon(FilamentIcon::resolve('actions::delete-action.modal') ?? 'heroicon-o-trash')
                ->icon(FilamentIcon::resolve('actions::delete-action') ?? 'heroicon-m-trash')
                ->successNotificationTitle('Drivers were successfully deleted')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function ($record) {
                    try {
                        $record->delete();

                        Notification::make()
                            ->title('Success')
                            ->body('Drivers were successfully deleted.')
                            ->success()
                            ->send();

                    } catch (QueryException $e) {
                        if ($e->getCode() === '23503') { // Foreign key violation

                            $driver = Driver::query()->whereIn('id', $e->getBindings())
                                ->first()
                                ->pluck('name')
                                ->filter()
                                ->map(fn ($name) => "'$name'")
                                ->join(', ');

                            Notification::make()
                                ->title('Cannot delete')
                                ->body("Cannot delete driver: $driver. He is used in tours.")
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Error')
                            ->body('An error occurred while deleting.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
