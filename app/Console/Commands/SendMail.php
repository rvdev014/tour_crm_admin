<?php

namespace App\Console\Commands;

use App\Mail\HotelMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendMail extends Command
{
    protected $signature = 'app:send-mail';

    protected $description = 'Command description';

    public function handle(): void
    {
        $expense = new \stdClass();
        $expense->comment = 'Test comment';
        $message = new HotelMail(now(), $expense, 10);
        Mail::to('ravshandavlatov014@gmail.com')->send($message);
    }
}
