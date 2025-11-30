<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Enums\TourType;
use App\Services\TourService;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

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
        /** @var Collection<Tour> $tours */
        $tours = Tour::query()->get();
        
        foreach ($tours as $tour) {
            $username = $tour->createdBy?->name ?? null;
            $firstLetter = substr($username, 0, 1);
            if ($tour->type == TourType::TPS) {
                $lastLetter = 'T';
            } else {
                $lastLetter = 'C';
            }
            
            $number = TourService::addHundred($tour->id);
            
            $currentYear = $tour->start_date ? Carbon::parse($tour->start_date)->format('y') : date('y');
            $tour->group_number = "{$firstLetter}{$number}-{$currentYear}{$lastLetter}";
            $tour->save();
            
            $this->info('Tour ID ' . $tour->id . ' group number updated to ' . $tour->group_number);
        }
    }
}
