<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AttachmentType: string implements HasLabel
{
    case Photo = 'photo';
    case Document = 'document';
    case Audio = 'audio';
    case Video = 'video';

    public function getLabel(): string
    {
        return match ($this) {
            self::Photo => 'Photo',
            self::Document => 'Document',
            self::Audio => 'Audio',
            self::Video => 'Video',
        };
    }
}
