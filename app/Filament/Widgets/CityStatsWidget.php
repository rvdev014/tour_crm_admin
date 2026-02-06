<?php

namespace App\Filament\Widgets;

use App\Models\City;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CityStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    
    protected static ?string $heading = 'Статистика по Городам';
    protected int | string | array $columnSpan = 1;
    protected static ?int $sort = 2;
    
    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => $this->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Город')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('stat_visits')
                    ->label('Кол-во туров')
                    ->sortable()
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('stat_nights')
                    ->label('Ночей')
                    ->sortable()
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('stat_people')
                    ->label('Туристов')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('stat_visits', 'desc');
    }
    
    protected function getQuery(): Builder
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $companyIds = $this->filters['company_ids'] ?? [];
        $hotelIds = $this->filters['hotel_ids'] ?? [];
        $cityIds = $this->filters['city_ids'] ?? [];
        
        // 1. Подзапрос: Считаем Pax (людей) для каждого TPS тура отдельно.
        // Это нужно, так как люди в TPS хранятся не в расходах, а глобально в туре.
        $tpsPaxQuery = DB::table('tour_room_types')
            ->select('tour_id', DB::raw('SUM(amount) as total_pax'))
            ->groupBy('tour_id');
        
        // 2. Основной агрегирующий запрос (Derived Table)
        // Мы группируем сначала по (city_id, tour_id), чтобы схлопнуть дубли внутри одного тура
        $statsQuery = DB::table('tour_day_expenses as tde')
            ->select([
                'tde.city_id',
                // Считаем уникальные туры (потом просуммируем строки)
                DB::raw('COUNT(DISTINCT t.id) as visits_count'),
                
                // Логика Ночей
                DB::raw('SUM(
                    CASE
                        WHEN t.type = 2 THEN tde.hotel_total_nights
                        WHEN t.type = 1 THEN 1 -- В группировке по дням каждая строка это 1 ночь (если distinct day)
                        ELSE 0
                    END
                ) as nights_sum'),
                
                // Логика Людей
                DB::raw('SUM(
                    CASE
                        -- Corp: берем сумму из room_types расхода
                        WHEN t.type = 2 THEN (
                            SELECT COALESCE(SUM(amount), 0)
                            FROM tour_day_expense_room_types
                            WHERE tour_day_expense_id = tde.id
                        )
                        -- TPS: берем заранее посчитанное число (max, т.к. оно одинаковое для всех дней тура)
                        WHEN t.type = 1 THEN COALESCE(tps_pax.total_pax, 0)
                        ELSE 0
                    END
                ) as people_sum')
            ])
            // JOIN'ы для сборки данных
            ->leftJoin('tour_days as td', 'tde.tour_day_id', '=', 'td.id')
            ->leftJoin('tour_groups as tg', 'tde.tour_group_id', '=', 'tg.id')
            ->join('tours as t', function($join) {
                // Умный JOIN: берем ID тура либо из дней, либо из групп
                $join->on('t.id', '=', DB::raw('COALESCE(td.tour_id, tg.tour_id)'));
            })
            // Подключаем подсчет людей для TPS
            ->leftJoinSub($tpsPaxQuery, 'tps_pax', 't.id', '=', 'tps_pax.tour_id')
            
            // --- ФИЛЬТРЫ (применяем ДО группировки для скорости) ---
            ->whereNotNull('tde.city_id')
            ->when($startDate, fn($q) => $q->where('t.start_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('t.start_date', '<=', $endDate))
            ->when($companyIds, fn($q) => $q->whereIn('t.company_id', $companyIds))
            ->when($cityIds, fn($q) => $q->whereIn('tde.city_id', $cityIds))
            ->when($hotelIds, fn($q) => $q->whereIn('tde.hotel_id', $hotelIds))
            
            // Если для TPS мы считаем ночи по дням, нам нужен Distinct по Day ID, чтобы не дублировать ночи
            // Для этого группируем по ключу уникальности события "Пребывание в городе"
            // В данном случае группируем по (city, tour, day/group), чтобы схлопнуть расходы (напр. Питание + Отель в один день)
            // Но чтобы упростить, сгруппируем по (City, Tour) и используем агрегаты.
            // ПРАВИЛЬНЫЙ ПОДХОД: Сначала группируем по City+Tour, чтобы получить 1 строку на 1 визит тура в город
            ->groupBy('tde.city_id', 't.id', 't.type', 'tps_pax.total_pax');
        
        
        // 3. Финальная обертка: Группируем данные чисто по городам
        $finalStats = DB::table( DB::raw("({$statsQuery->toSql()}) as raw_stats") )
            ->mergeBindings($statsQuery) // Важно! Переносим параметры (даты, ID)
            ->select([
                'city_id',
                DB::raw('COUNT(*) as stat_visits'), // Кол-во строк = кол-во уникальных туров
                DB::raw('SUM(nights_sum) as stat_nights'), // TPS тут требует внимания * (см. ниже)
                DB::raw('SUM(people_sum) as stat_people'),
            ])
            ->groupBy('city_id');
        
        
        // * КОРРЕКТИРОВКА ПО НОЧАМ TPS:
        // В statsQuery выше для TPS мы суммируем 1 для каждой строки расхода.
        // Если у тура в один день 2 расхода (Отель + Ужин), будет 2. Это ошибка.
        // Исправляем $statsQuery ниже для точности.
        
        return $this->getOptimizedJoinQuery($startDate, $endDate, $companyIds, $cityIds, $hotelIds);
    }
    
    // Вынес чистую оптимизированную логику в отдельный метод
    protected function getOptimizedJoinQuery($startDate, $endDate, $companyIds, $cityIds, $hotelIds): Builder
    {
        // 1. Subquery для TPS Pax (кол-во людей в туре)
        $tpsPax = DB::table('tour_room_types')
            ->select('tour_id', DB::raw('SUM(amount) as val'))
            ->groupBy('tour_id');
        
        // 2. Subquery агрегации по (City, Tour).
        // Это самая важная часть. Мы получаем "Сколько ночей и людей принес ЭТОТ тур ЭТОМУ городу".
        $cityTourStats = DB::table('tour_day_expenses as tde')
            ->select('tde.city_id')
            ->selectRaw('1 as visit_val') // Одна строка здесь = один визит тура
            // Ночи:
            ->selectRaw("SUM(CASE
                WHEN t.type = 2 THEN tde.hotel_total_nights
                ELSE 0 END) as corp_nights")
            // Для TPS ночей считаем уникальные дни (tour_day_id)
            ->selectRaw("COUNT(DISTINCT CASE
                WHEN t.type = 1 AND tde.hotel_id IS NOT NULL THEN tde.tour_day_id
                ELSE NULL END) as tps_nights")
            // Люди:
            ->selectRaw("SUM(CASE
                WHEN t.type = 2 THEN (SELECT SUM(amount) FROM tour_day_expense_room_types WHERE tour_day_expense_id = tde.id)
                ELSE 0 END) as corp_people")
            // Для TPS людей берем MAX, так как join размножает строки, а кол-во людей одно на тур
            ->selectRaw("MAX(CASE WHEN t.type = 1 THEN COALESCE(pax.val, 0) ELSE 0 END) as tps_people")
            
            ->leftJoin('tour_days as td', 'tde.tour_day_id', '=', 'td.id')
            ->leftJoin('tour_groups as tg', 'tde.tour_group_id', '=', 'tg.id')
            ->join('tours as t', 't.id', '=', DB::raw('COALESCE(td.tour_id, tg.tour_id)'))
            ->leftJoinSub($tpsPax, 'pax', 't.id', '=', 'pax.tour_id')
            
            ->whereNotNull('tde.city_id')
            // Фильтры
            ->when($startDate, fn($q) => $q->where('t.start_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('t.start_date', '<=', $endDate))
            ->when($companyIds, fn($q) => $q->whereIn('t.company_id', $companyIds))
            ->when($cityIds, fn($q) => $q->whereIn('tde.city_id', $cityIds))
            ->when($hotelIds, fn($q) => $q->whereIn('tde.hotel_id', $hotelIds))
            
            ->groupBy('tde.city_id', 't.id');
        
        // 3. Финальный запрос к Cities с JOIN-ом результата
        return City::query()
            ->joinSub($cityTourStats, 'stats', 'cities.id', '=', 'stats.city_id')
            ->select('cities.name', 'cities.id')
            ->selectRaw('SUM(stats.visit_val) as stat_visits')
            ->selectRaw('SUM(stats.corp_nights + stats.tps_nights) as stat_nights')
            ->selectRaw('SUM(stats.corp_people + stats.tps_people) as stat_people')
            ->groupBy('cities.id', 'cities.name');
    }
}