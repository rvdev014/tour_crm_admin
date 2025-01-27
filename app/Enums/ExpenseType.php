<?php

namespace App\Enums;

enum ExpenseType: int
{
    case Hotel = 1;
    case Guide = 2;
    case Transport = 3;
    case Museum = 4;
    case Lunch = 5;
    case Dinner = 6;
    case Extra = 7;
    case Train = 8;
    case Plane = 9;
    case Show = 10;
    case Conference = 11;

    public static function casesOptions(): array
    {
        return [
            1 => self::Hotel->getLabel(),
            2 => self::Guide->getLabel(),
            3 => self::Transport->getLabel(),
            4 => self::Museum->getLabel(),
            5 => self::Lunch->getLabel(),
            6 => self::Dinner->getLabel(),
            8 => self::Train->getLabel(),
            9 => self::Plane->getLabel(),
            10 => self::Show->getLabel(),
            11 => self::Conference->getLabel(),
            7 => self::Extra->getLabel(),
        ];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Hotel => 'Hotel',
            self::Guide => 'Guide',
            self::Transport => 'Transport',
            self::Museum => 'Museum',
            self::Lunch => 'Lunch',
            self::Dinner => 'Dinner',
            self::Extra => 'Extra',
            self::Train => 'Train',
            self::Plane => 'Plane',
            self::Show => 'Show',
            self::Conference => 'Conference',
        };
    }

    public function getCssClass(): string
    {
        return match ($this) {
            self::Hotel => 'bg-blue-500',
            self::Guide => 'bg-green-500',
            self::Transport => 'bg-yellow-500',
            self::Museum => 'bg-red-500',
            self::Lunch => 'bg-pink-500',
            self::Dinner => 'bg-purple-500',
            self::Extra => throw new \Exception('To be implemented'),
            self::Train => throw new \Exception('To be implemented'),
            self::Plane => throw new \Exception('To be implemented'),
            self::Show => throw new \Exception('To be implemented'),
            self::Conference => throw new \Exception('To be implemented'),
        };
    }
}
