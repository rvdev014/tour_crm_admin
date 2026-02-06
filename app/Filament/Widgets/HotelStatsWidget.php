<?php

namespace App\Filament\Widgets;

use App\Models\Hotel;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HotelStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    
    protected static ?string $heading = 'Загрузка Отелей';
    protected int | string | array $columnSpan = 1;
    protected static ?int $sort = 3;
    
    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => $this->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Отель')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('stat_visits')
                    ->label('Заездов')
                    ->description('Кол-во туров')
                    ->sortable()
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('stat_nights')
                    ->label('Продано ночей')
                    ->sortable()
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('stat_people')
                    ->label('Размещено людей')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('stat_nights', 'desc');
    }
    
    protected function getQuery(): Builder
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $companyIds = $this->filters['company_ids'] ?? [];
        $cityIds = $this->filters['city_ids'] ?? [];
        $hotelIds = $this->filters['hotel_ids'] ?? [];
        
        // 1. Предварительный подсчет людей для TPS туров (global pax per tour)
        $tpsPax = DB::table('tour_room_types')
            ->select('tour_id', DB::raw('SUM(amount) as val'))
            ->groupBy('tour_id');
        
        // 2. Агрегация по (Отель + Тур).
        // Это "Атомарный заезд". Один тур в один отель.
        // Здесь мы схлопываем многодневное проживание в одну строку статистики.
        $hotelTourStats = DB::table('tour_day_expenses as tde')
            ->select('tde.hotel_id')
            
            // Заезды: 1 строка здесь = 1 уникальный тур в этом отеле
            ->selectRaw('1 as visit_val')
            
            // Ночи:
            ->selectRaw("SUM(CASE
                -- Corporate: берем поле из расхода
                WHEN t.type = 2 THEN tde.hotel_total_nights
                ELSE 0 END) as corp_nights")
            ->selectRaw("COUNT(DISTINCT CASE
                -- TPS: Считаем количество дней (строк), проведенных в этом отеле
                WHEN t.type = 1 THEN tde.tour_day_id
                ELSE NULL END) as tps_nights")
            
            // Люди:
            ->selectRaw("SUM(CASE
                -- Corporate: Суммируем конкретную разбивку по комнатам для этого расхода
                WHEN t.type = 2 THEN (SELECT SUM(amount) FROM tour_day_expense_room_types WHERE tour_day_expense_id = tde.id)
                ELSE 0 END) as corp_people")
            ->selectRaw("MAX(CASE
                -- TPS: Берем ОБЩЕЕ кол-во людей тура (Max, т.к. оно не меняется от дня к дню)
                -- Мы берем MAX, а не SUM, потому что сгруппировали по туру.
                WHEN t.type = 1 THEN COALESCE(pax.val, 0)
                ELSE 0 END) as tps_people")
            
            // Joins для доступа к данным тура
            ->leftJoin('tour_days as td', 'tde.tour_day_id', '=', 'td.id')
            ->leftJoin('tour_groups as tg', 'tde.tour_group_id', '=', 'tg.id')
            ->join('tours as t', 't.id', '=', DB::raw('COALESCE(td.tour_id, tg.tour_id)'))
            // Присоединяем кол-во людей для TPS
            ->leftJoinSub($tpsPax, 'pax', 't.id', '=', 'pax.tour_id')
            
            // Обязательно фильтруем пустые hotel_id
            ->whereNotNull('tde.hotel_id')
            
            // --- ФИЛЬТРЫ ---
            ->when($startDate, fn($q) => $q->where('t.start_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('t.start_date', '<=', $endDate))
            ->when($companyIds, fn($q) => $q->whereIn('t.company_id', $companyIds))
            ->when($cityIds, fn($q) => $q->whereIn('tde.city_id', $cityIds))
            ->when($hotelIds, fn($q) => $q->whereIn('tde.hotel_id', $hotelIds))
            
            // Группируем по Отелю и Туру, чтобы получить "один заезд"
            ->groupBy('tde.hotel_id', 't.id');
        
        // 3. Финальный запрос к таблице Отелей
        return Hotel::query()
            ->joinSub($hotelTourStats, 'stats', 'hotels.id', '=', 'stats.hotel_id')
            ->select([
                'hotels.name',
                'hotels.id',
                DB::raw('SUM(stats.visit_val) as stat_visits'),
                DB::raw('SUM(stats.corp_nights + stats.tps_nights) as stat_nights'),
                DB::raw('SUM(stats.corp_people + stats.tps_people) as stat_people'),
            ])
            ->groupBy('hotels.id', 'hotels.name');
    }
}