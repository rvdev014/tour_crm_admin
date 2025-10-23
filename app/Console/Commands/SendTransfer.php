<?php

namespace App\Console\Commands;

use App\Models\TransferRequest;
use App\Services\TourService;
use App\Services\TransferService;
use Carbon\Carbon;
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
//        $transferRequest = TransferRequest::query()->find(29);
//        app(TransferService::class)->acceptRequest($transferRequest);
        app(TransferService::class)->notifyClientsForTransfer();
//        app(TourService::class)->notifyDrivers();
    }
}
