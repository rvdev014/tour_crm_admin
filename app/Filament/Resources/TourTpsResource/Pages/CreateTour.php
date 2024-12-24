<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Filament\Resources\TourTpsResource;
use App\Models\Transfer;
use App\Services\TourService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateTour extends CreateRecord
{
    protected static string $resource = TourTpsResource::class;
    protected static ?string $title = 'Create Tour TPS';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = TourType::TPS;
        $data['group_number'] = TourService::getGroupNumber(TourType::TPS);
        $data['created_by'] = auth()->id();

        $days = collect($this->form->getRawState()['days'] ?? []);
        $totalExpenses = $days->flatMap(fn($day) => $day['expenses'])->sum('price');

        $data['expenses'] = $totalExpenses;
        $data['income'] = $data['price'] - $totalExpenses;

        $transfers = $days->flatMap(fn($day) => $day['expenses'])->filter(fn($expense) => $expense['type'] == ExpenseType::Transport->value);
        if (!empty($transfers)) {
            foreach ($transfers as $transfer) {
                Transfer::create([
                    'from_city_id' => $transfer['from_city_id'],
                    'to_city_id' => $transfer['to_city_id'],
                    'comment' => $transfer['comment'],
                    'company_id' => $data['company_id'],
                    'group_number' => $data['group_number'],
                    'transport_type' => $transfer['transport_type'],
                    'transport_comfort_level' => $transfer['transport_comfort_level'],
                    'price' => $transfer['price'],
                    'status' => $transfer['status'],
                ]);
            }
        }

        return $data;
    }
}
