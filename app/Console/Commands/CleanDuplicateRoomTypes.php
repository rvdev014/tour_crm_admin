<?php

namespace App\Console\Commands;

use App\Models\RoomType;
use App\Models\HotelRoomType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDuplicateRoomTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'room-types:clean-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean all duplicate named RoomTypes and update related HotelRoomType records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of duplicate RoomTypes...');

        DB::transaction(function () {
            // Find duplicate room types grouped by name
            $duplicateGroups = RoomType::select('name')
                ->groupBy('name')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('name');

            if ($duplicateGroups->isEmpty()) {
                $this->info('No duplicate RoomTypes found.');
                return;
            }

            $totalCleaned = 0;

            foreach ($duplicateGroups as $roomTypeName) {
                $this->info("Processing duplicates for: {$roomTypeName}");

                // Get all room types with this name, ordered by ID (keep the first one)
                $roomTypes = RoomType::where('name', $roomTypeName)
                    ->orderBy('id')
                    ->get();

                // Keep the first (oldest) room type
                $keepRoomType = $roomTypes->first();
                $duplicateRoomTypes = $roomTypes->skip(1);

                $this->info("  Keeping RoomType ID: {$keepRoomType->id}");

                foreach ($duplicateRoomTypes as $duplicateRoomType) {
                    $this->info("  Removing duplicate RoomType ID: {$duplicateRoomType->id}");

                    // Update all HotelRoomType records that reference the duplicate
                    $affectedHotelRoomTypes = HotelRoomType::where('room_type_id', $duplicateRoomType->id)->count();
                    
                    if ($affectedHotelRoomTypes > 0) {
                        HotelRoomType::where('room_type_id', $duplicateRoomType->id)
                            ->update(['room_type_id' => $keepRoomType->id]);
                        
                        $this->info("    Updated {$affectedHotelRoomTypes} HotelRoomType records");
                    }

                    // Delete the duplicate room type
                    $duplicateRoomType->delete();
                    $totalCleaned++;
                }
            }

            $this->info("Cleanup completed. Removed {$totalCleaned} duplicate RoomTypes.");
        });

        // Show final statistics
        $this->showStatistics();
    }

    private function showStatistics()
    {
        $this->info('');
        $this->info('Final statistics:');
        
        $totalRoomTypes = RoomType::count();
        $this->info("Total RoomTypes: {$totalRoomTypes}");

        $uniqueNames = RoomType::distinct('name')->count();
        $this->info("Unique RoomType names: {$uniqueNames}");

        if ($totalRoomTypes === $uniqueNames) {
            $this->info('✅ All RoomTypes now have unique names!');
        } else {
            $this->warn('⚠️  There may still be some duplicates.');
        }
    }
}