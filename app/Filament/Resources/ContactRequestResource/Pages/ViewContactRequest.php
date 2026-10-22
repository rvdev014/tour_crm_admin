<?php

namespace App\Filament\Resources\ContactRequestResource\Pages;

use App\Enums\WebTourStatus;
use App\Filament\Resources\ContactRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewContactRequest extends ViewRecord
{
    protected static string $resource = ContactRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('email')
                ->label(__('Send email'))
                ->icon('heroicon-o-envelope')
                ->url(fn() => ContactRequestResource::mailto($this->record)),
            Actions\Action::make('set_new')
                ->label(__('New'))
                ->color('gray')
                ->hidden(fn() => $this->record->status === WebTourStatus::New)
                ->action(fn() => $this->changeStatus(WebTourStatus::New)),
            Actions\Action::make('set_in_process')
                ->label(__('In process'))
                ->color('warning')
                ->hidden(fn() => $this->record->status === WebTourStatus::Waiting)
                ->action(fn() => $this->changeStatus(WebTourStatus::Waiting)),
            Actions\Action::make('set_done')
                ->label(__('Done'))
                ->color('success')
                ->hidden(fn() => $this->record->status === WebTourStatus::Done)
                ->action(fn() => $this->changeStatus(WebTourStatus::Done)),
        ];
    }

    private function changeStatus(WebTourStatus $status): void
    {
        ContactRequestResource::setStatus($this->record, $status);
        $this->record->refresh();
    }
}
