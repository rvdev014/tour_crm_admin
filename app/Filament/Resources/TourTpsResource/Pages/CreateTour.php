<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

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

        $days = collect($this->form->getRawState()['days'] ?? []);
        $allExpenses = $days->flatMap(fn($day) => $day['expenses']);

        $tourStatus = TourStatus::Confirmed;
        foreach ($allExpenses as $expense) {
            $status = $expense['status'] ?? null;
            if ($status == ExpenseStatus::New->value || $status == ExpenseStatus::Waiting->value) {
                $tourStatus = TourStatus::NotConfirmed;
                break;
            }
        }
        $data['status'] = $tourStatus;

        $totalExpenses = $allExpenses->sum('price') + ($data['guide_price'] ?? 0);
        $data['expenses'] = $totalExpenses;
        $data['income'] = $data['price'] - $totalExpenses;

        TourService::sendMails($data, $days);

        return $data;
    }

    protected function afterCreate(): void
    {
        $roomTypeAmounts = ExpenseService::getRoomingAmounts($this->form->getRawState());
        foreach ($roomTypeAmounts as $roomTypeId => $amount) {
            if (empty($amount)) {
                continue;
            }
            TourRoomType::query()->updateOrCreate(
                [
                    'tour_id' => $this->record->id,
                    'room_type_id' => $roomTypeId,
                ],
                [
                    'amount' => $amount,
                ]
            );
        }
    }
}
