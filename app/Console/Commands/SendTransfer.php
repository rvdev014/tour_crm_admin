<?php

namespace App\Console\Commands;

use App\Services\TourService;
use Illuminate\Console\Command;

class SendTransfer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-transfer';

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
        app(TourService::class)->notifyDrivers();
    }
}
