<?php

namespace App\Console\Commands;

use App\Http\Controllers\AmoCrm\AmoCrmSendController;
use Illuminate\Console\Command;

class Send extends Command {

    protected $signature = 'send:send';

    protected $description = 'Отправка данных в AmoCRM';

    public function handle() {
        $controllerAmoCrmSend = new AmoCrmSendController();

        $controllerAmoCrmSend->getCreateLeads(); // Экспорт новых сделкок в Амо

        return "Success";
    }
}
