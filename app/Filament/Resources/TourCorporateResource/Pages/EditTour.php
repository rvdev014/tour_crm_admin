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

        $newTransfers = $days->flatMap(fn($day) => $day['expenses'])
            ->filter(fn($expense) => $expense['type'] == ExpenseType::Transport->value && empty($expense['id']));

        if (!empty($newTransfers)) {
            foreach ($newTransfers as $transfer) {
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

        $updatedTransfers = $days->flatMap(fn($day) => $day['expenses'])
            ->filter(fn($expense) => $expense['type'] == ExpenseType::Transport->value && !empty($expense['id']));

        if (!empty($updatedTransfers)) {
            foreach ($updatedTransfers as $transfer) {
                Transfer::query()->where($transfer['id'])->update([
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
