<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Laravel\Facades\Telegram;

class LogProvider extends ServiceProvider {

    public static function log(string $message): void {
        try {
            Telegram::sendMessage([
                'chat_id' => 228519769,
                'text' => $message
            ]);
        } catch (\Exception $e) {

        }
    }

}
