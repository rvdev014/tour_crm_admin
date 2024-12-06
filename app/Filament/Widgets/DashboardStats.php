<?php

namespace App\Filament\Widgets;

use App\Enums\TourType;
use App\Models\Tour;
use App\Models\TourDayExpense;
use App\Services\TourService;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

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
            $this->getTpsIncomeStat($startDate, $endDate, $countryId),
            $this->getCorporateIncomeStat($startDate, $endDate, $countryId),
        ];
    }

    public function getToursStat(Carbon $startDate, Carbon $endDate, $countryId, TourType $tourType): Stat
    {
        $totalTours = TourService::getTotalCount($tourType, $startDate, $endDate, $countryId);

        $lastMonthTotalTours = TourService::getTotalCount(
            $tourType,
            $prevStartDate = $startDate->copy()->subMonth(),
            $prevStartDate->copy()->endOfMonth(),
            $countryId
        );

        return $this->getStat(
            "Total tours {$tourType->getLabel()}",
            self::format($totalTours),
            $totalTours - $lastMonthTotalTours
        );
    }

    public function getTpsIncomeStat(Carbon $startDate, Carbon $endDate, $countryId): Stat
    {
        $totalIncome = TourService::getTpsTotalIncome($startDate, $endDate, $countryId);

        $prevTotalIncome = TourService::getTpsTotalIncome(
            $prevStartDate = $startDate->copy()->subMonth(),
            $prevStartDate->copy()->endOfMonth(),
            $countryId
        );

        return $this->getStat(
            "Tours TPS profit",
            '$' . self::format($totalIncome),
            $totalIncome - $prevTotalIncome
        );
    }

    public function getCorporateIncomeStat(Carbon $startDate, Carbon $endDate, $countryId): Stat
    {
        $totalIncome = TourService::getCorporateTotalIncome($startDate, $endDate, $countryId);

        $prevTotalIncome = TourService::getCorporateTotalIncome(
            $prevStartDate = $startDate->copy()->subMonth(),
            $prevStartDate->copy()->endOfMonth(),
            $countryId
        );

        return $this->getStat(
            "Tours Corporate profit",
            '$' . self::format($totalIncome),
            $totalIncome - $prevTotalIncome
        );
    }

    public function getStat(string $label, $total, $difference): Stat
    {
        $icon = $difference > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $color = $difference > 0 ? 'success' : 'danger';
        $chart = $difference > 0 ? [7, 2, 10, 3, 15, 4, 17] : [17, 4, 15, 3, 10, 2, 7];

        return Stat::make($label, $total)->icon($icon)->color('success');
    }

    public static function format($value): string
    {
        if ($value > 1000) {
            return number_format($value / 1000, 2, ',', '') . 'K';
        }

        return number_format($value);
    }
}
