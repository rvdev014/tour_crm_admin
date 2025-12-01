<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\Hotel;
use App\Models\HotelPeriod;
use App\Models\HotelRoomType;
use App\Models\ManualPhone;
use App\Models\Review;
use Illuminate\Console\Command;

class PopulateHotelRelations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hotels:populate-relations 
                           {--force : Force repopulation even if relations exist}
                           {--hotels= : Comma-separated list of hotel IDs to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate missing relations (facilities, reviews, room types, periods, phones) for existing hotels';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏨 Starting hotel relations population...');

        $force = $this->option('force');
        $hotelIds = $this->option('hotels') ? explode(',', $this->option('hotels')) : null;

        // Get hotels to process
        $hotelsQuery = Hotel::query();
        if ($hotelIds) {
            $hotelsQuery->whereIn('id', $hotelIds);
            $this->info("Processing specific hotels: " . implode(', ', $hotelIds));
        }

        $hotels = $hotelsQuery->get();
        
        if ($hotels->isEmpty()) {
            $this->error('No hotels found to process.');
            return self::FAILURE;
        }

        $this->info("Found {$hotels->count()} hotels to process.");

        $progressBar = $this->output->createProgressBar($hotels->count());
        $progressBar->start();

        $stats = [
            'processed' => 0,
            'facilities_added' => 0,
            'reviews_added' => 0,
            'room_types_added' => 0,
            'periods_added' => 0,
            'phones_added' => 0,
        ];

        foreach ($hotels as $hotel) {
            $this->processHotel($hotel, $force, $stats);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info('✅ Population completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Hotels Processed', $stats['processed']],
                ['Facilities Added', $stats['facilities_added']],
                ['Reviews Added', $stats['reviews_added']],
                ['Room Types Added', $stats['room_types_added']],
                ['Periods Added', $stats['periods_added']],
                ['Phones Added', $stats['phones_added']],
            ]
        );

        return self::SUCCESS;
    }

    private function processHotel(Hotel $hotel, bool $force, array &$stats): void
    {
        $stats['processed']++;

        // Add facilities
        if ($force || $hotel->facilities()->count() === 0) {
            $facilityCount = rand(5, 15);
            $facilities = Facility::factory()->count($facilityCount)->create();
            $attachCount = min(rand(3, 8), $facilityCount);
            $hotel->facilities()->attach($facilities->random($attachCount));
            $stats['facilities_added'] += $attachCount;
        }

        // Add reviews
        if ($force || $hotel->reviews()->count() === 0) {
            $reviewCount = rand(3, 10);
            Review::factory()
                ->count($reviewCount)
                ->for($hotel, 'reviewable')
                ->create();
            $stats['reviews_added'] += $reviewCount;
        }

        // Add room types
        if ($force || $hotel->roomTypes()->count() === 0) {
            $roomTypeCount = rand(2, 5);
            HotelRoomType::factory()
                ->count($roomTypeCount)
                ->for($hotel)
                ->create();
            $stats['room_types_added'] += $roomTypeCount;
        }

        // Add periods
        if ($force || $hotel->periods()->count() === 0) {
            $periodCount = rand(2, 4);
            HotelPeriod::factory()
                ->count($periodCount)
                ->for($hotel)
                ->create();
            $stats['periods_added'] += $periodCount;
        }

        // Add phones
        if ($force || $hotel->phones()->count() === 0) {
            $phoneCount = rand(1, 3);
            ManualPhone::factory()
                ->count($phoneCount)
                ->for($hotel, 'manual')
                ->create();
            $stats['phones_added'] += $phoneCount;
        }
    }
}
