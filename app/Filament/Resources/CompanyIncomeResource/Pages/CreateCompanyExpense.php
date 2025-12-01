<?php

namespace App\Filament\Resources\CompanyIncomeResource\Pages;

use App\Filament\Resources\CompanyIncomeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCompanyExpense extends CreateRecord
{
    protected static string $resource = CompanyIncomeResource::class;
}
