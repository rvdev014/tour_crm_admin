<?php

namespace App\Console;

use App\Services\LotService;
use App\Services\TourService;
use App\Services\PaymentService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule
            ->call(function() {
                app(TourService::class)->notifyDrivers();
            })
            ->everyMinute()
            ->onSuccess(function() {
                echo 'The task was successful';
            })
            ->onFailure(function() {
                echo 'The task failed';
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
