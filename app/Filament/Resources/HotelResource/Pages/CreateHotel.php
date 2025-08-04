<?php

namespace App\Filament\Resources\HotelResource\Pages;

use App\Filament\Resources\HotelResource;
use App\Models\Hotel;
use App\Services\FileService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateHotel extends CreateRecord
{
    protected static string $resource = HotelResource::class;

    protected function afterCreate(): void
    {
        $formState = $this->form->getState();
        /** @var Hotel $hotel */
        $hotel = $this->getRecord();

        /** @var TemporaryUploadedFile[] $photos */
        $photos = $formState['photos'] ?? [];
        foreach ($photos as $photo) {
            $attachmentData = FileService::createAttachmentFromFile($photo);
            $hotel->attachments()->create($attachmentData);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
