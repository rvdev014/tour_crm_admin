<?php

namespace App\Filament\Widgets;

use App\Enums\TourType;
use App\Models\Country;
use App\Models\Tour;
use Filament\Widgets\ChartWidget;

class ToursTpsChart extends ChartWidget
{
    protected static ?string $heading = 'Tour TPS incomes';
    protected static string $color = 'success';

    protected function getData(): array
    {
        return [
            'datasets' => $this->getDataSets(),
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    public function getDataSets(): array
    {
        /** @var Country $countries */
        $countries = Country::query()
            ->select(['id', 'name'])
            ->whereHas('tours', fn($q) => $q->where('type', TourType::TPS))
            ->get();

        $result = [];
        foreach ($countries as $country) {
            $data = [];
            for ($i = 1; $i <= 12; $i++) {
                $data[] = Tour::query()
                    ->whereMonth('start_date', $i)
                    ->where('type', TourType::TPS)
                    ->where('country_id', $country->id)
                    ->sum('income');
            }

            $result[] = [
                'label' => $country->name,
                'data' => $data
            ];
        }

        return $result;
    }

    protected function getType(): string
    {
        return 'line';
    }
}
