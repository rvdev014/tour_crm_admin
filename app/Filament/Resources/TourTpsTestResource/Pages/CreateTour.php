<?php

namespace App\Filament\Resources\TourTpsTestResource\Pages;

use App\Enums\CurrencyEnum;
use App\Enums\ExpenseStatus;
use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Filament\Resources\TourTpsResource;
use App\Filament\Resources\TourTpsTestResource;
use App\Models\TourRoomType;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourTpsTestResource::class;
    protected static ?string $title = 'Create Tour TPS';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = TourType::TPS;
        $data['created_by'] = auth()->id();
        $data['status'] = TourStatus::NotConfirmed;

        ExpenseService::convertExpensePrice($data, 'price');
        ExpenseService::convertExpensePrice($data, 'guide_price');

        $price = $data['price_converted'] ?? $data['price'] ?? 0;
        $guidePrice = $data['guide_price_converted'] ?? $data['guide_price'] ?? 0;
        $data['price'] = $price + $guidePrice;

        return $data;
    }

    protected function afterCreate(): void
    {
        ExpenseService::createTourRoomTypes($this->record->id, $this->form->getRawState());
        TourService::sendTelegram($this->form->getRawState());
    }
}
