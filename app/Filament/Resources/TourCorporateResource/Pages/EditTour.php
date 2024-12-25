<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Enums\ExpenseType;
use App\Filament\Resources\TourCorporateResource;
use App\Models\Transfer;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourCorporateResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $days = collect($this->form->getRawState()['days'] ?? []);
        $totalExpenses = $days->flatMap(fn($day) => $day['expenses'])->sum('price');

        $data['expenses'] = $totalExpenses;
        $data['income'] = $data['price'] - $totalExpenses;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
