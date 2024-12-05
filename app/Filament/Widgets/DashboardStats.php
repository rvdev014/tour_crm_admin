<?php

namespace App\Filament\Widgets;

use App\Enums\TourType;
use App\Models\Tour;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $startDate = Carbon::parse($this->filters['start_date']) ?? now()->startOfMonth();
        $endDate = Carbon::parse($this->filters['end_date']) ?? now()->endOfMonth();
        $countryId = $this->filters['country'] ?? null;

        return [
            $this->getToursStat($startDate, $endDate, $countryId, TourType::TPS),
            $this->getToursStat($startDate, $endDate, $countryId, TourType::Corporate),
//            $this->getToursIncomeStat($startDate, $endDate, $countryId, TourType::Corporate),
        ];
    }

    public function getToursStat(Carbon $startDate, Carbon $endDate, $countryId, TourType $tourType): Stat
    {
        $toursTpsQuery = Tour::query()
            ->where('type', $tourType)
            ->when($countryId, fn($query, $countryId) => $query->where('country_id', $countryId));

        $totalTours = $toursTpsQuery->clone()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $prevStartDate = $startDate->copy()->subMonth();
        $prevEndDate = $prevStartDate->copy()->endOfMonth();

        $lastMonthTotalTours = $toursTpsQuery->clone()
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->count();

        $difference = $totalTours - $lastMonthTotalTours;

        $icon = $difference > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $color = $difference > 0 ? 'success' : 'danger';
        $chart = $difference > 0 ? [7, 2, 10, 3, 15, 4, 17] : [17, 4, 15, 3, 10, 2, 7];

        return Stat::make("Total tours {$tourType->getLabel()}", self::format($totalTours))
            ->description("$difference increase")
            ->descriptionIcon($icon)
            ->chart($chart)
            ->color($color);
    }

    public function getToursIncomeStat(Carbon $startDate, Carbon $endDate, $countryId, TourType $tourType): Stat
    {
        $toursTpsQuery = Tour::query()
            ->where('type', $tourType)
            ->when($countryId, fn($query, $countryId) => $query->where('country_id', $countryId));

        $totalTours = $toursTpsQuery->clone()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('income');

        $prevStartDate = $startDate->copy()->subMonth();
        $prevEndDate = $prevStartDate->copy()->endOfMonth();

        $lastMonthTotalTours = $toursTpsQuery->clone()
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->count();

        $difference = $totalTours - $lastMonthTotalTours;

        $icon = $difference > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $color = $difference > 0 ? 'success' : 'danger';
        $chart = $difference > 0 ? [7, 2, 10, 3, 15, 4, 17] : [17, 4, 15, 3, 10, 2, 7];

        return Stat::make("Total tours {$tourType->getLabel()}", self::format($totalTours))
            ->description("$difference increase")
            ->descriptionIcon($icon)
            ->chart($chart)
            ->color($color);
    }

    public static function format($value): string
    {
        return number_format($value);
    }
}
