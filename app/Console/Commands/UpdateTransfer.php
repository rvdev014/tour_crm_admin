<?php

namespace App\Console\Commands;

use App\Models\Transfer;
use App\Services\TourService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class UpdateTransfer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-transfer';

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
        /** @var Collection<Transfer> $transfers */
        $transfers = Transfer::query()->get();

        foreach ($transfers as $transfer) {
            $transfer->update(['sell_price' => $transfer->price]);
        }

        $this->info('Transfers updated successfully.');
    }
}
