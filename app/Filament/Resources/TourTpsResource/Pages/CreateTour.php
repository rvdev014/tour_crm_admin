<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Enums\CurrencyEnum;
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
}
