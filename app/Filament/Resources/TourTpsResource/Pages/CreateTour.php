<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\CurrencyEnum;
use App\Enums\ExpenseStatus;
use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Filament\Resources\TourTpsResource;
use App\Models\TourRoomType;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourTpsResource::class;
    protected static ?string $title = 'Create Tour TPS';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = TourType::TPS;
        $data['created_by'] = auth()->id();
        $data['status'] = TourStatus::NotConfirmed;

        ExpenseService::convertExpensePrice($data, 'price');
        ExpenseService::convertExpensePrice($data, 'guide_price');
        ExpenseService::convertExpensePrice($data, 'transport_price');

        $totalPax = $data['pax'] + ($data['leader_pax'] ?? 0);

        $data['price_result'] = $data['price_converted'] ?? $data['price'] ?? 0;
        $data['total_price'] = round($data['price_result'] * $totalPax, 2);

        $data['guide_price_result'] = $data['guide_price_converted'] ?? $data['guide_price'] ?? 0;
        $data['transport_price_result'] = $data['transport_price_converted'] ?? $data['transport_price'] ?? 0;

        $data['expenses_total'] = $data['guide_price_result'];
        $data['income'] = $data['total_price'] - $data['expenses_total'];

        return $data;
    }

    protected function afterCreate(): void
    {
        ExpenseService::createTourRoomTypes($this->record->id, $this->form->getRawState());
        TourService::sendTelegram($this->form->getRawState());
    }
}
