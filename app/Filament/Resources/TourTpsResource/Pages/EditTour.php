<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\ExpenseStatus;
use App\Enums\TourStatus;
use App\Filament\Resources\TourTpsResource;
use App\Models\TourRoomType;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourTpsResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
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

        $roomTypeAmounts = ExpenseService::getRoomingAmounts($data);
        foreach ($roomTypeAmounts as $roomTypeId => $amount) {
            $tourHotelRoomType = TourRoomType::query()
                ->where('tour_id', $this->record->id)
                ->where('room_type_id', $roomTypeId)
                ->first();

            if ($tourHotelRoomType) {
                if (empty($amount)) {
                    $tourHotelRoomType->delete();
                } else {
                    $tourHotelRoomType->update(['amount' => $amount]);
                }
            } else {
                if (!empty($amount)) {
                    TourRoomType::query()->create([
                        'tour_id' => $this->record->id,
                        'room_type_id' => $roomTypeId,
                        'amount' => $amount,
                    ]);
                }
            }
        }

        TourService::sendMails($data, $days);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
//            Actions\Action::make('export_client')
//                ->label('Report for Client')
//                ->icon('heroicon-o-document-text')
//                ->url(route('export-client', $this->record)),
            Actions\Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-document-text')
                ->url(route('export', $this->record)),
            Actions\DeleteAction::make()
                ->label('Delete')
                ->icon('heroicon-o-trash'),
        ];
    }
}
