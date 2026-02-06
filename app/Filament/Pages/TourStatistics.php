<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm; // Важный трейт
use Filament\Pages\Page;
use App\Models\City;
use App\Models\Hotel;
use App\Models\Company;

class TourStatistics extends \Filament\Pages\Dashboard
{
    // Подключаем трейт для работы фильтров
    use HasFiltersForm;
    
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $title = 'Статистика Туров';
    protected static ?string $navigationLabel = 'Аналитика';
//    protected static string $view = 'filament.pages.tour-statistics';
    
    // Регистрируем виджеты, которые будут на этой странице
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\CompanyStatsWidget::class,
            \App\Filament\Widgets\CityStatsWidget::class,
            \App\Filament\Widgets\HotelStatsWidget::class,
        ];
    }
    
    // Настройка колонок сетки (опционально, по умолчанию 2)
    public function getColumns(): int | string | array
    {
        return 2; // Чтобы таблицы были широкими
    }
    
    // Схема формы фильтров
    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(5)->schema([
                    DatePicker::make('startDate')
                        ->label('C даты'),
                    DatePicker::make('endDate')
                        ->label('По дату'),
                    Select::make('company_ids')
                        ->label('Компании')
                        ->multiple()
                        ->options(Company::pluck('name', 'id'))
                        ->searchable(),
                    Select::make('city_ids')
                        ->label('Города')
                        ->multiple()
                        ->options(City::pluck('name', 'id'))
                        ->searchable(),
                    Select::make('hotel_ids')
                        ->label('Отели')
                        ->multiple()
                        ->options(Hotel::pluck('name', 'id'))
                        ->searchable(),
                ])
            ]);
    }
}