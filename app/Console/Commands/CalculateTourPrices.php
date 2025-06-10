<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Enums\TourType;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class CalculateTourPrices extends Command
{
    protected $signature = 'tour:calculate-prices';

    protected $description = 'Calculate total prices for TPS tours';

    public function handle(): void
    {
        /** @var Collection<Tour> $tours */
        $tours = Tour::query()->where('type', TourType::TPS)->get();

        foreach ($tours as $tour) {
            $tour->saveExpensesTotal(true);
        }

        $this->info('Tour prices calculated successfully.');
    }
}
