<?php

namespace App\Console\Commands;

use App\Models\Transfer;
use App\Services\TourService;
use Illuminate\Console\Command;

class GenNumberTransfers extends Command
{
    protected $signature = 'app:gen-number-transfers';

    protected $description = 'Command description';

    public function handle(): void
    {
        foreach (Transfer::query()->whereNull('number')->get() as $transfer) {
            $transfer->number = 1000 + $transfer->id;
            $transfer->save();
            $this->info("Transfer ID {$transfer->id} number set to {$transfer->number}");
        }
    }
}
