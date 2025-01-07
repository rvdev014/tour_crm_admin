<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\ExpenseType;
use App\Filament\Resources\TourTpsResource;
use App\Models\TourHotelRoomType;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    use SaveTour;

    protected static string $resource = TourTpsResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $days = collect($this->form->getRawState()['days'] ?? []);
        $totalPax = $data['pax'] + ($data['leader_pax'] ?? 0);
        $expensesData = $this->getExpensesData($days, $totalPax);

        $hotelExpenses = $expensesData->filter(fn($expense) => $expense['type'] == ExpenseType::Hotel->value);
        $roomTypeAmounts = $this->getRoomingAmounts($data);
        $hotelExpensesTotal = $this->getHotelExpensesTotal($hotelExpenses, $roomTypeAmounts);

        $totalExpenses = $expensesData->sum('price') + $hotelExpensesTotal + ($data['guide_price'] ?? 0);

        $data['hotel_expenses_total'] = $hotelExpensesTotal;
        $data['expenses'] = $totalExpenses;
        $data['income'] = $data['price'] - $totalExpenses;

        foreach ($roomTypeAmounts as $roomTypeId => $amount) {
            $tourHotelRoomType = TourHotelRoomType::query()
                ->where('tour_id', $this->record->id)
                ->where('hotel_room_type_id', $roomTypeId)
                ->first();

            if ($tourHotelRoomType) {
                if (empty($amount)) {
                    $tourHotelRoomType->delete();
                } else {
                    $tourHotelRoomType->update(['amount' => $amount]);
                }
            } else {
                if (!empty($amount)) {
                    TourHotelRoomType::query()->create([
                        'tour_id' => $this->record->id,
                        'hotel_room_type_id' => $roomTypeId,
                        'amount' => $amount,
                    ]);
                }
            }
        }

        $this->sendMails($data, $days);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
