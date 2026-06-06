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
        $startDate = filled($this->filters['start_date'] ?? null)
            ? Carbon::parse($this->filters['start_date'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $endDate = filled($this->filters['end_date'] ?? null)
            ? Carbon::parse($this->filters['end_date'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $current  = $this->getTransferStats($startDate, $endDate);

        $prevStart = $startDate->copy()->subMonth()->startOfDay();
        $prevEnd   = $prevStart->copy()->endOfMonth()->endOfDay();
        $previous  = $this->getTransferStats($prevStart, $prevEnd);

        $countDiff = $current['count'] - $previous['count'];
        $valueDiff = $current['total'] - $previous['total'];

        return [
            Stat::make('Transfers (period)', $current['count'])
                ->description($countDiff >= 0 ? "+{$countDiff} vs prev month" : "{$countDiff} vs prev month")
                ->icon($countDiff >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($countDiff >= 0 ? 'success' : 'danger'),

            Stat::make('Transfers total (USD)', '$' . DashboardStats::format($current['total']))
                ->description($valueDiff >= 0 ? '+$' . DashboardStats::format($valueDiff) . ' vs prev month' : '-$' . DashboardStats::format(abs($valueDiff)) . ' vs prev month')
                ->icon($valueDiff >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($valueDiff >= 0 ? 'success' : 'danger'),
        ];
    }

    private function getTransferStats(Carbon $startDate, Carbon $endDate): array
    {
        $query = Transfer::query()->whereBetween('created_at', [$startDate, $endDate]);

        return [
            'count' => $query->count(),
            'total' => (float) $query->sum('price'),
        ];
    }
}
