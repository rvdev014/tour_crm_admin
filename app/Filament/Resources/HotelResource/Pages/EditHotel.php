<?php

namespace App\Filament\Resources\HotelResource\Pages;

use App\Filament\Resources\HotelResource;
use App\Models\Hotel;
use App\Services\FileService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditHotel extends EditRecord
{
    protected static string $resource = HotelResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $formState = $this->form->getRawState();
        // Process coordinates field
        if (!empty($formState['coordinates'])) {
            $coordinates = explode(',', $formState['coordinates']);
            if (count($coordinates) === 2) {
                $data['latitude'] = trim($coordinates[0]);
                $data['longitude'] = trim($coordinates[1]);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $formState = $this->form->getState();
        /** @var Hotel $hotel */
        $hotel = $this->getRecord();

        /** @var (TemporaryUploadedFile|string)[] $photos */
        $photos = $formState['photos'] ?? [];
        foreach ($hotel->attachments as $oldAttachment) {
            if (!in_array($oldAttachment->file_path, $photos)) {
                $oldAttachment->delete();
            }
        }

        foreach ($photos as $photo) {
            if (is_string($photo)) continue;
            $attachmentData = FileService::createAttachmentFromFile($photo);
            $hotel->attachments()->create($attachmentData);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
