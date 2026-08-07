<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WhatsAppDirection: string implements HasLabel
{
    case In = 'in';
    case Out = 'out';

    public function getLabel(): string
    {
        return match ($this) {
            self::In => 'Incoming',
            self::Out => 'Outgoing',
        };
    }
}
