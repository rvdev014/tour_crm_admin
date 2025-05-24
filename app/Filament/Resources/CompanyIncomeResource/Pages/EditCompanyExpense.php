<?php

namespace App\Filament\Resources\CompanyIncomeResource\Pages;

use App\Filament\Resources\CompanyIncomeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCompanyExpense extends EditRecord
{
    protected static string $resource = CompanyIncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
