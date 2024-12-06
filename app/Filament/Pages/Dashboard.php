<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ToursCorporateChart;
use App\Filament\Widgets\ToursTpsChart;
use App\Filament\Widgets\DashboardStats;
use App\Models\Country;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        $startMonth = $this->filters['start_date'] ? Carbon::parse($this->filters['start_date']) : now()->startOfMonth();
        $endMonth = $this->filters['end_date'] ? Carbon::parse($this->filters['end_date']) : now()->endOfMonth();

        return $form->schema([
            Grid::make(3)->schema([
                DatePicker::make('start_date')->formatStateUsing(fn() => $startMonth->format('Y-m-d')),
                DatePicker::make('end_date')->formatStateUsing(fn() => $endMonth->format('Y-m-d')),
                Select::make('country')->options(Country::pluck('name', 'id')->toArray()),
            ])
        ]);
    }

    public function getWidgets(): array
    {
        return [
            DashboardStats::class,
            ToursTpsChart::class,
            ToursCorporateChart::class,
        ];
    }
}
