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
    case Other = 7;
    case Train = 8;
    case Plane = 9;
    case Show = 10;
    case Conference = 11;

    public function getLabel(): string
    {
        return match ($this) {
            self::Hotel => 'Hotel',
            self::Guide => 'Guide',
            self::Transport => 'Transport',
            self::Museum => 'Ticket',
            self::Lunch => 'Lunch',
            self::Dinner => 'Dinner',
            self::Other => 'Other',
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
        };
    }
}
