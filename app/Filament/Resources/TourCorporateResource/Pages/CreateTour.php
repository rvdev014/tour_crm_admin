<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Enums\TourType;
use App\Filament\Resources\TourCorporateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    use SaveTourCorporate;

    protected static string $resource = TourCorporateResource::class;
    protected static ?string $title = 'Create Tour Corporate';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = TourType::Corporate;
        $data['created_by'] = auth()->id();
        $totalPax = $this->form->getRawState()['passengers'] ?? 0;

        $days = collect($this->form->getRawState()['days'] ?? []);
        $expensesData = $this->getExpensesData($days, $totalPax);
        $totalExpenses = $expensesData->sum('price');

        // Calculate total expenses and income

        $data['expenses'] = $totalExpenses;
        $data['income'] = $data['price'] - $totalExpenses;

//        TourService::sendMails($data, $days);

        return $data;
    }
}
