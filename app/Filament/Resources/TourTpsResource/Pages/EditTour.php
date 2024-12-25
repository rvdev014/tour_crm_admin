<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\ExpenseType;
use App\Filament\Resources\TourTpsResource;
use App\Models\Transfer;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

class EditTour extends EditRecord
{
    protected static string $resource = TourTpsResource::class;

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
