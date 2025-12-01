<?php

namespace App\Services;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FileService
{
    public static function createAttachmentFromFile(TemporaryUploadedFile $file): array
    {
        return [
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $file->store('attachments', 'public'),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ];
    }
}
