<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public static function sendMessage(string $username, string $message): void
    {
        // Send message to Telegram
        try {
            Http::post('https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN') . '/sendMessage', [
                'chat_id' => $username,
                'text' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::channel('telegram')->error($e->getMessage());
        }
    }
}
