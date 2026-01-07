<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\ToursCorporateChart;
use App\Filament\Widgets\ToursTpsChart;
use App\Models\Country;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Support\Carbon;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;


    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->isAdmin() || auth()->user()->isSeniorOperator();
    }

    public static function canAccess(): bool
    {
        return auth()->user()->isAdmin() || auth()->user()->isSeniorOperator();
    }

    public function filtersForm(Form $form): Form
    {
        $startDate = $this->filters['start_date'] ?? null;
        $endDate = $this->filters['end_date'] ?? null;

        $startMonth = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $endMonth = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();

        return $form->disabled(fn() => auth()->user()->isOperator())->schema([
            Grid::make(3)->schema([
                DatePicker::make('start_date')
                    ->formatStateUsing(fn() => $startMonth->format('d-m-Y'))
                    ->displayFormat('d.m.Y')
                    ->afterStateUpdated(function($get, $set) {
                        if (Carbon::parse($get('end_date')) < Carbon::parse($get('start_date'))) {
                            $set('end_date', null);
                        }
                    })
                    ->reactive()
                    ->native(false),
                DatePicker::make('end_date')
                    ->formatStateUsing(fn() => $endMonth->format('d-m-Y'))
                    ->displayFormat('d.m.Y')
                    ->minDate(fn ($get) => $get('start_date'))
                    ->reactive()
                    ->native(false),
                Select::make('country')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->options(Country::pluck('name', 'id')->toArray()),
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
