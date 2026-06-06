<?php

namespace App\Filament\Widgets;

use App\Models\Transfer;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransferStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $startDate = Carbon::parse($this->filters['start_date']) ?? now()->startOfMonth();
        $endDate = Carbon::parse($this->filters['end_date']) ?? now()->endOfMonth();

        $current = $this->getTransferStats($startDate, $endDate);

        $prevStart = $startDate->copy()->subMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();
        $previous = $this->getTransferStats($prevStart, $prevEnd);

        $countDiff = $current['count'] - $previous['count'];
        $valueDiff = $current['total'] - $previous['total'];

        return [
            Stat::make('Transfers (period)', $current['count'])
                ->icon($countDiff >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($countDiff >= 0 ? 'success' : 'danger'),

            Stat::make('Transfers total (USD)', '$' . DashboardStats::format($current['total']))
                ->icon($valueDiff >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($valueDiff >= 0 ? 'success' : 'danger'),
        ];
    }

    private function getTransferStats(Carbon $startDate, Carbon $endDate): array
    {
        $query = Transfer::query()
            ->whereBetween('date_time', [$startDate->startOfDay(), $endDate->endOfDay()]);

        return [
            'count' => $query->count(),
            'total' => (float) $query->sum('price'),
        ];
    }
}
