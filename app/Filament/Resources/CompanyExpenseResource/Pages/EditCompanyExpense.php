<?php

namespace App\Filament\Resources\CompanyExpenseResource\Pages;

use App\Filament\Resources\CompanyExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCompanyExpense extends EditRecord
{
    protected static string $resource = CompanyExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
