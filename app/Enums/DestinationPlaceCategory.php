<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\App;

/**
 * Group a "place to see" card belongs to on a destination page
 * ("Top outdoor experiences", "Top historical experiences", …).
 */
enum DestinationPlaceCategory: string implements HasLabel
{
    case Outdoor = 'outdoor';
    case Historical = 'historical';
    case Cultural = 'cultural';
    case Other = 'other';

    /** Label used in the admin form (follows the admin's locale). */
    public function getLabel(): string
    {
        return match ($this) {
            self::Outdoor => __('Outdoor'),
            self::Historical => __('Historical'),
            self::Cultural => __('Cultural'),
            self::Other => __('Other'),
        };
    }

    /** Group heading shown to website visitors, in the request's language. */
    public function heading(): string
    {
        $ru = App::getLocale() === 'ru';

        return match ($this) {
            self::Outdoor => $ru ? 'Лучшие впечатления на природе' : 'Top outdoor experiences',
            self::Historical => $ru ? 'Лучшие исторические впечатления' : 'Top historical experiences',
            self::Cultural => $ru ? 'Лучшие культурные впечатления' : 'Top cultural experiences',
            self::Other => $ru ? 'Ещё стоит увидеть' : 'More to see',
        };
    }

    public static function fromValueOrOther(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Other;
    }
}
