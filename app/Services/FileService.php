<?php

namespace App\Services;

use App\Enums\AttachmentType;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FileService
{
    public static function createAttachmentFromFile(
        TemporaryUploadedFile $file,
        AttachmentType $category = AttachmentType::Photo,
        string $directory = 'attachments'
    ): array {
        return [
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $file->store($directory, 'public'),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'category' => $category,
        ];
    }
}
