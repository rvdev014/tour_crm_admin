<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CompanyStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    
    protected static ?string $heading = 'Статистика по Компаниям';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 1;
    
    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => $this->getQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Компания')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('total_tours_count')
                    ->label('Туров')
                    ->alignCenter()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('total_nights_calculated')
                    ->label('Ночей')
                    ->description('В выбранных городах/отелях')
                    ->alignCenter()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('total_people_calculated')
                    ->label('Туристов')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->defaultSort('total_tours_count', 'desc');
    }
    
    protected function getQuery(): Builder
    {
        // 1. Получаем значения фильтров
        $companyIds = $this->filters['company_ids'] ?? [];
        $cityIds = $this->filters['city_ids'] ?? [];
        $hotelIds = $this->filters['hotel_ids'] ?? [];
        
        // 2. Генерируем SQL условия для вставки в RAW запросы
        // Мы используем псевдоним таблицы 'expenses_alias' который передадим в функцию
        $cityConditionTde = $this->getExpenseConditionSql($cityIds, $hotelIds, 'tde');   // для Corporate
        $cityConditionTde2 = $this->getExpenseConditionSql($cityIds, $hotelIds, 'tde2'); // для TPS
        
        return Company::query()
            // --- ФИЛЬТР 1: Оставляем только выбранные компании ---
            ->when($companyIds, fn($q) => $q->whereIn('id', $companyIds))
            
            // --- ФИЛЬТР 2: Глобальный фильтр (убираем компании без подходящих туров) ---
            // Если выбраны города или отели, проверяем, есть ли у компании хоть один тур туда
            ->when($cityIds || $hotelIds, function ($q) use ($cityIds, $hotelIds) {
                $q->whereHas('tours', function ($tourQ) use ($cityIds, $hotelIds) {
                    $tourQ->where(function ($sub) use ($cityIds, $hotelIds) {
                        // Проверяем путь TPS (через Days)
                        $sub->whereHas('days.expenses', fn($exp) => $this->applyExpenseFilters($exp, $cityIds, $hotelIds))
                            // Проверяем путь Corporate (через Groups)
                            ->orWhereHas('groups.expenses', fn($exp) => $this->applyExpenseFilters($exp, $cityIds, $hotelIds));
                    });
                });
            })
            
            // --- ПОДСЧЕТЫ (Subqueries) ---
            ->addSelect([
                'id',
                'name',
                
                // 1. КОЛИЧЕСТВО ТУРОВ
                // Считаем тур, если он подходит по дате И имеет расходы в выбранном городе/отеле
                'total_tours_count' => DB::table('tours')
                    ->selectRaw('count(*)')
                    ->whereColumn('company_id', 'companies.id')
                    ->tap(fn($q) => $this->applyDateFilters($q))
                    // Важно: если выбран город, мы не считаем туры, которые в этот город не заезжали
                    ->when($cityIds || $hotelIds, function ($q) use ($cityIds, $hotelIds) {
                        $q->where(function($sub) use ($cityIds, $hotelIds) {
                            $sub->whereExists(function($ex) use ($cityIds, $hotelIds) {
                                // TPS Check
                                $ex->select(DB::raw(1))
                                    ->from('tour_days')
                                    ->join('tour_day_expenses', 'tour_days.id', '=', 'tour_day_expenses.tour_day_id')
                                    ->whereColumn('tour_days.tour_id', 'tours.id')
                                    ->when($cityIds, fn($sq) => $sq->whereIn('tour_day_expenses.city_id', $cityIds))
                                    ->when($hotelIds, fn($sq) => $sq->whereIn('tour_day_expenses.hotel_id', $hotelIds));
                            })
                                ->orWhereExists(function($ex) use ($cityIds, $hotelIds) {
                                    // Corporate Check
                                    $ex->select(DB::raw(1))
                                        ->from('tour_groups')
                                        ->join('tour_day_expenses', 'tour_groups.id', '=', 'tour_day_expenses.tour_group_id')
                                        ->whereColumn('tour_groups.tour_id', 'tours.id')
                                        ->when($cityIds, fn($sq) => $sq->whereIn('tour_day_expenses.city_id', $cityIds))
                                        ->when($hotelIds, fn($sq) => $sq->whereIn('tour_day_expenses.hotel_id', $hotelIds));
                                });
                        });
                    }),
                
                // 2. КОЛИЧЕСТВО НОЧЕЙ (Nights)
                // Суммируем ночи только из расходов, которые совпадают с фильтром города/отеля
                'total_nights_calculated' => DB::table('tours')
                    ->selectRaw("SUM(
                        CASE
                            -- Corporate Path
                            WHEN tours.type = 2 THEN (
                                SELECT COALESCE(SUM(tde.hotel_total_nights), 0)
                                FROM tour_groups tg
                                INNER JOIN tour_day_expenses tde ON tg.id = tde.tour_group_id
                                WHERE tg.tour_id = tours.id
                                AND tde.hotel_id IS NOT NULL
                                {$cityConditionTde} -- Вставка условия: AND tde.city_id IN (...)
                            )
                            -- TPS Path
                            WHEN tours.type = 1 THEN (
                                SELECT COUNT(DISTINCT td.id)
                                FROM tour_days td
                                INNER JOIN tour_day_expenses tde2 ON td.id = tde2.tour_day_id
                                WHERE td.tour_id = tours.id
                                AND tde2.hotel_id IS NOT NULL
                                {$cityConditionTde2} -- Вставка условия: AND tde2.city_id IN (...)
                            )
                            ELSE 0
                        END
                    )")
                    ->whereColumn('company_id', 'companies.id')
                    ->tap(fn($q) => $this->applyDateFilters($q)),
                
                // 3. КОЛИЧЕСТВО ЛЮДЕЙ (People/Pax)
                'total_people_calculated' => DB::table('tours')
                    ->selectRaw("SUM(
                        CASE
                            -- Corporate Path
                            WHEN tours.type = 2 THEN (
                                SELECT COALESCE(SUM(tdert.amount), 0)
                                FROM tour_groups tg
                                INNER JOIN tour_day_expenses tde ON tg.id = tde.tour_group_id
                                INNER JOIN tour_day_expense_room_types tdert
                                    ON tde.id = tdert.tour_day_expense_id
                                WHERE tg.tour_id = tours.id
                                AND tde.hotel_id IS NOT NULL
                                {$cityConditionTde}
                            )
                            -- TPS Path
                            -- Сложность: tour_room_types привязан к туру, а не к расходу.
                            -- Если мы фильтруем по городу, мы должны считать людей,
                            -- только если тур вообще заезжал в этот город.
                            WHEN tours.type = 1 THEN (
                                SELECT COALESCE(SUM(trt.amount), 0)
                                FROM tour_room_types trt
                                WHERE trt.tour_id = tours.id
                                AND EXISTS (
                                    SELECT 1 FROM tour_days td
                                    JOIN tour_day_expenses tde2 ON td.id = tde2.tour_day_id
                                    WHERE td.tour_id = tours.id
                                    {$cityConditionTde2}
                                )
                            )
                            ELSE 0
                        END
                    )")
                    ->whereColumn('company_id', 'companies.id')
                    ->tap(fn($q) => $this->applyDateFilters($q)),
            ]);
    }
    
    /**
     * Помощник для генерации raw SQL условия WHERE IN (...)
     */
    protected function getExpenseConditionSql(array $cityIds, array $hotelIds, string $tableAlias): string
    {
        $sql = "";
        
        if (!empty($cityIds)) {
            // Превращаем массив [1, 2] в строку "1,2"
            $ids = implode(',', array_map('intval', $cityIds));
            $sql .= " AND {$tableAlias}.city_id IN ({$ids})";
        }
        
        if (!empty($hotelIds)) {
            $ids = implode(',', array_map('intval', $hotelIds));
            $sql .= " AND {$tableAlias}.hotel_id IN ({$ids})";
        }
        
        return $sql;
    }
    
    /**
     * Помощник для Eloquent (whereHas)
     */
    protected function applyExpenseFilters($query, $cityIds, $hotelIds)
    {
        if (!empty($cityIds)) {
            $query->whereIn('city_id', $cityIds);
        }
        if (!empty($hotelIds)) {
            $query->whereIn('hotel_id', $hotelIds);
        }
    }
    
    protected function applyDateFilters($query)
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        
        if ($startDate) $query->whereDate('start_date', '>=', $startDate);
        if ($endDate) $query->whereDate('start_date', '<=', $endDate);
        
        return $query;
    }
}