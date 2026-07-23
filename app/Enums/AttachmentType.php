<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AttachmentType: string implements HasLabel
{
    case Photo = 'photo';
    case Document = 'document';

    public function getLabel(): string
    {
        return match ($this) {
            self::Photo => 'Photo',
            self::Document => 'Document',
        };
    }
}
