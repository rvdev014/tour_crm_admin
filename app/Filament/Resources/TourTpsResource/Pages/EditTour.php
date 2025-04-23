<?php

namespace App\Filament\Resources\TourTpsResource\Pages;

use App\Filament\Resources\TourTpsResource;
use App\Filament\Resources\TourTpsResource\Actions\SendMailAction;
use App\Models\Tour;
use App\Services\ExpenseService;
use App\Services\TourService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourTpsResource::class;
}
