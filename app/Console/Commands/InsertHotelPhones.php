<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Models\ManualPhone;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class InsertHotelPhones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:hotel-phones';

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
        /** @var Collection<ManualPhone> $manualPhones */
        $manualPhones = ManualPhone::query()->whereNotNull('hotel_id')->get();

        if ($manualPhones->isEmpty()) {
            $this->info('No manual phones found with hotel_id.');
            return;
        }

        foreach ($manualPhones as $manualPhone) {
            $manualPhone->update([
                'hotel_id' => null,
                'manual_type' => Hotel::class,
                'manual_id' => $manualPhone->hotel_id,
            ]);
        }

        $this->info('Manual phones updated successfully.');
    }
}
