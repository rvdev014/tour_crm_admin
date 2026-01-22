<?php

namespace App\Console\Commands;

use App\Models\Tour;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateTourNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-tour-numbers';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        echo "Начало перенумерации за 2026 год...\n";
        
        $counter = 100;
        
        Tour::query()
            ->whereYear('start_date', 2026)
            ->orderBy('created_at', 'asc')
            ->chunkById(50, function($tours) use (&$counter) {
                foreach ($tours as $tour) {
                    // Первая буква (Менеджер)
                    $firstLetter = substr($tour->group_number, 0, 1);
                    
                    // Последняя буква (T или C)
                    $lastLetter = substr($tour->group_number, -1);
                    
                    // Формируем новый номер
                    $newGroupNumber = "{$firstLetter}{$counter}-26{$lastLetter}";
                    
                    // Обновляем напрямую через DB, чтобы не сработали Observer-ы и события
                    DB::table('tours')
                        ->where('id', $tour->id)
                        ->update(['group_number' => $newGroupNumber]);
                    
                    echo "ID {$tour->id}: {$tour->group_number} -> {$newGroupNumber}\n";
                    
                    $counter++;
                }
            });
        
        echo "Готово! Обработано туров: " . ($counter - 101) . "\n";
    }
}
