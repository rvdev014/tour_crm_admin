<?php

namespace App\Filament\Widgets;

use App\Enums\TourType;
use App\Models\Country;
use App\Models\Tour;
use Filament\Forms\Components\Select;
use Filament\Widgets\ChartWidget;

class ToursTpsChart extends ChartWidget
{
    protected static ?string $heading = 'Tour TPS incomes';
    protected static string $color = 'success';

    public ?string $country_id = null;

    protected function getFormSchema(): array
    {
        return [
            Select::make('country_id')
                ->label('Country')
                ->options(
                    Country::query()
                        ->whereHas('tours', fn($q) => $q->where('type', TourType::TPS))
                        ->pluck('name', 'id')
                        ->toArray()
                )
                ->live(),
        ];
    }


    protected function getData(): array
    {
        return [
            'datasets' => $this->getDataSets(),
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    public function getDataSets(): array
    {
        $countriesQuery = Country::query()
            ->select(['id', 'name'])
            ->whereHas('tours', fn($q) => $q->where('type', TourType::TPS));

        if ($this->country_id) {
            $countriesQuery->where('id', $this->country_id);
        }

        $countries = $countriesQuery->get();


        $result = [];
        foreach ($countries as $country) {
            $data = [];
            for ($i = 1; $i <= 12; $i++) {
                $data[] = Tour::query()
                    ->whereMonth('created_at', $i)
                    ->whereYear('created_at', date('Y'))
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
