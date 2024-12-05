<?php

namespace App\Enums;

enum ExpenseType: int
{
    case Hotel = 1;
    case Guide = 2;
    case Transport = 3;
    case Ticket = 4;
    case Lunch = 5;
    case Dinner = 6;
    case Other = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::Hotel => 'Hotel',
            self::Guide => 'Guide',
            self::Transport => 'Transport',
            self::Ticket => 'Ticket',
            self::Lunch => 'Lunch',
            self::Dinner => 'Dinner',
            self::Other => 'Other',
        };
    }

    public function getCssClass(): string
    {
        return match ($this) {
            self::Hotel => 'bg-blue-500',
            self::Guide => 'bg-green-500',
            self::Transport => 'bg-yellow-500',
            self::Ticket => 'bg-red-500',
            self::Lunch => 'bg-pink-500',
            self::Dinner => 'bg-purple-500',
        };
    }
}
