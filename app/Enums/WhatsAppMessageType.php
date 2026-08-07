<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WhatsAppMessageType: string implements HasLabel
{
    case Text = 'text';
    case Image = 'image';
    case Document = 'document';
    case Audio = 'audio';
    case Video = 'video';
    case Sticker = 'sticker';
    case Location = 'location';
    case Template = 'template';
    case Unsupported = 'unsupported';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Image => 'Image',
            self::Document => 'Document',
            self::Audio => 'Audio',
            self::Video => 'Video',
            self::Sticker => 'Sticker',
            self::Location => 'Location',
            self::Template => 'Template',
            self::Unsupported => 'Unsupported',
        };
    }

    public function hasMedia(): bool
    {
        return in_array($this, [self::Image, self::Document, self::Audio, self::Video, self::Sticker], true);
    }
}
