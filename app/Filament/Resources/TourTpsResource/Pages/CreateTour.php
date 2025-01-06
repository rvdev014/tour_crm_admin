<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Filament\Resources\TourTpsResource;
use App\Models\TourHotelRoomType;
use App\Services\TourService;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    use SaveTour;

    protected static string $resource = TourTpsResource::class;
    protected static ?string $title = 'Create Tour TPS';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = TourType::TPS;
        $data['group_number'] = TourService::getGroupNumber(TourType::TPS);
        $data['created_by'] = auth()->id();
        $totalPax = $data['pax'] + ($data['leader_pax'] ?? 0);

        $days = collect($this->form->getRawState()['days'] ?? []);
        $expensesData = $this->getExpensesData($days, $totalPax);

        $hotelExpenses = $expensesData->filter(fn($expense) => $expense['type'] == ExpenseType::Hotel->value);
        $roomTypeAmounts = $this->getRoomingAmounts($data);
        $hotelExpensesTotal = $this->getHotelExpensesTotal($hotelExpenses, $roomTypeAmounts);

        $totalExpenses = $expensesData->sum('price') + $hotelExpensesTotal + ($data['guide_price'] ?? 0);

        $data['hotel_expenses_total'] = $hotelExpensesTotal;
        $data['expenses'] = $totalExpenses;
        $data['income'] = $data['price'] - $totalExpenses;

        $this->sendMails($expensesData);

        return $data;
    }

    protected function afterCreate(): void
    {
        $roomTypeAmounts = $this->getRoomingAmounts($this->form->getRawState());
        foreach ($roomTypeAmounts as $roomTypeId => $amount) {
            if (empty($amount)) {
                continue;
            }
            TourHotelRoomType::query()->updateOrCreate(
                [
                    'tour_id' => $this->record->id,
                    'hotel_room_type_id' => $roomTypeId,
                ],
                [
                    'amount' => $amount,
                ]
            );
        }
    }
}
