<?php

namespace App\Console\Commands;

use App\Models\Hotel;
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
        /** @var Collection<Hotel> $hotels */
        $hotels = Hotel::query()->get();

        foreach ($hotels as $hotel) {
            if ($hotel->phones()->where(['phone_number' => $hotel->phone])->doesntExist()) {
                $hotel->phones()->create(['phone_number' => $hotel->phone]);
            }
        }
    }
}
