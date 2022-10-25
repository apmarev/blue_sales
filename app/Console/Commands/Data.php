<?php

namespace App\Console\Commands;

use App\Http\Controllers\AmoCrm\AmoCrmSendController;
use App\Models\Clients;
use App\Models\Export;
use App\Models\Orders;
use App\Providers\LogProvider;
use Illuminate\Console\Command;
use App\Http\Controllers\AmoCrm\AmoCrmContactsController;
use App\Http\Controllers\BlueSales\BlueSalesController;
use App\Http\Controllers\Import\ImportController;

class Data extends Command {

    protected $signature = 'data:save';

    protected $description = 'Command description';

    public function handle() {

        LogProvider::log("Начало операции");

        $controllerAmoCrm = new AmoCrmContactsController();
        $controllerBlueSales = new BlueSalesController();
        $controllerAmoCrmSend = new AmoCrmSendController();

        Export::truncate();
        Clients::truncate();
        Orders::truncate();

        $controllerAmoCrm->contactsPull(); // Скачивание всех контактов из АмоСрм
        $controllerBlueSales->getClients(); // Скачивание клиентов из BlueSales за вчерашний день
        $controllerBlueSales->getOrdersYesterday(); // Скачивание всех заказов из BlueSales

        LogProvider::log("Загрузка данных из AmoCRM и BlueSales завершена");

        $controllerBlueSales->getAndRemoveClients(); // Удаление дубляжей клиентов
        LogProvider::log("Дубликаты удалены");

        $controllerBlueSales->checkClientsFromBS(); // Подготовка данных клиентов к экспорту в амо
        $controllerBlueSales->checkOrdersFromBS(); // Подготовка данных заказов к экспорту в амо

        LogProvider::log("Данные к экспорту в AmoCrm подготовлены");
        //
        $count = Export::where('type', 'lead')->where('action', 'create')->count();
        $controllerAmoCrmSend->getCreateLeads(); // Экспорт новых сделок в Амо
        LogProvider::log("Создано сделок: {$count}");

        $count = Export::where('type', 'lead')->where('action', 'update')->count();
        $controllerAmoCrmSend->getUpdateLeads(); // Экспорт обновленных сделок в Амо
        LogProvider::log("Изменено сделок: {$count}");

        $count = Export::where('type', 'all')->where('action', 'create')->count();
        $controllerAmoCrmSend->getCreateLeadsAndContacts(); // Экспорт сделок с контактами в Амо
        LogProvider::log("Создано сделок вместе с новыми контактами: {$count}");

        LogProvider::log("Операция завершена");

        return "Success";
    }
}
