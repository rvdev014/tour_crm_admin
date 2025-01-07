<?php

namespace App\Filament\Resources\TourCorporateResource\Pages;

use App\Enums\ExpenseType;
use App\Models\Museum;
use App\Models\Restaurant;
use App\Models\Show;

trait SaveTourCorporate
{
    protected function getExpensesData($days, $totalPax)
    {
        return $days->flatMap(fn($day) => $day['expenses'])->map(function ($expense) use ($totalPax) {
            switch ($expense['type']) {
                case ExpenseType::Museum->value:
                    /** @var Museum $museum */
                    $museum = Museum::query()->find($expense['museum_item_id'] ?? $expense['museum_id']);
                    if ($museum) {
                        $expense['price'] = $museum->price_per_person * $totalPax;
                    }
                    return $expense;

                case ExpenseType::Lunch->value:
                case ExpenseType::Dinner->value:
                    /** @var Restaurant $restaurant */
                    $restaurant = Restaurant::query()->find($expense['restaurant_id']);
                    if ($restaurant) {
                        $expense['price'] = $restaurant->price_per_person * $totalPax;
                    }
                    return $expense;

                case ExpenseType::Show->value:
                    /** @var Show $show */
                    $show = Show::query()->find($expense['show_id']);
                    if ($show) {
                        $expense['price'] = $show->price_per_person * $totalPax;
                    }
                    return $expense;

                default:
                    return $expense;
            }
        });
    }
}
