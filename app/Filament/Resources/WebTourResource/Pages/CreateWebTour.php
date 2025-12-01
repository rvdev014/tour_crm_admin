<?php

namespace App\Filament\Resources\WebTourResource\Pages;

use App\Filament\Resources\TourResource;
use App\Filament\Resources\WebTourResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateWebTour extends CreateRecord
{
    protected static string $resource = WebTourResource::class;
}
