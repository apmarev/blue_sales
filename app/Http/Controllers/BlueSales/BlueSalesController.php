<?php

namespace App\Http\Controllers\BlueSales;

use App\Http\Controllers\Controller;
use App\Models\Clients;
use App\Models\Contacts;
use App\Models\Export;
use App\Providers\LogProvider;
use Illuminate\Support\Facades\Http;
use App\Models\Orders;
use Illuminate\Http\Client\Pool;
use GuzzleHttp\Exception\ConnectException;
use App\Http\Controllers\AmoCrm\AmoCrmLeadsController;

/**
 * Немного фактов об АПИ BlueSales (далее BS) для того несчастного,
 * кто это читает и не знаком со спецификой и древностью BS:
 *
 * 1. Их АПИ принимает параметры только POST методом, поэтому любой запрос к их АПИ является POST запросом.
 * 2. Их АПИ не имеет иного кода ответа, помимо 200. И в случае успеха и в случае ошибки.
 *    В случае ошибки они просто вываливают кучу html с кодом ответа 200.
 * 3. Если передавать их АПИ offset, то чем он больше, тем дольше ждать ответ. Иногда по 30 минут. Проверено.
 *
 * А теперь вишенка на торте и она же - объяснение всему вышеперечисленному:
 * Заказы, контакты и все прочие сущности ВСЕХ их клиентов хранятся в одной базе данных. Да, именно.
 * От этого база у них весит, по их же словам, десятки террабайт. Ну и писалось у них все безумно давно и
 * они ничего с этим не делают и делать не будут (опять же, это они мне сказали).
 *
 * Так что приходится работать с тем что имеем.
 */

class BlueSalesController extends Controller {

    protected static string $url = 'https://bluesales.ru/app/';
    protected static string $login = '';
    protected static string $password = '';

    protected function sendToExport(array $array) {

        $amoLeadsController = new AmoCrmLeadsController();
        $orderChange = [];

        foreach($array as $order) {
            $orderChange[] = [
                'id' => $order['id'],
                'account_id' => $order['account_id'],
                'update' => false
            ];
            if($order['vk_id']) {
                if($contact = Contacts::where('vk_id', $order['vk_id'])->first()) {
                    $leadsIds = json_decode($contact['leads']);
                    $leads = $amoLeadsController->getLeadsByIds($leadsIds);

                    $checkLeads = $amoLeadsController->checkLeads($leads, $order['vk_group']);

                    if($checkLeads) {
                        $export = new Export();
                        $export->__set('action', 'update');
                        $export->__set('type', 'lead');
                        $export->__set('entity', json_encode(AmoCrmLeadsController::templateUpdateLead($order, $checkLeads)));
                        $export->save();
                        // LogProvider::log("Редактировать сделку");
                    } else {
                        $export = new Export();
                        $export->__set('action', 'create');
                        $export->__set('type', 'lead');
                        $export->__set('entity', json_encode(AmoCrmLeadsController::templateCreateLead($order, $contact)));
                        $export->save();
                        // LogProvider::log("Создать сделку");
                    }
                } else {
                    $export = new Export();
                    $export->__set('action', 'create');
                    $export->__set('type', 'all');
                    $export->__set('entity', json_encode(AmoCrmLeadsController::templateCreateContactAndLead($order)));
                    $export->save();
                    // LogProvider::log("Создать контакт и сделку");
                }
            }

        }

        return $orderChange;
    }

    protected function sendClientsToExport(array $array) {
        $amoLeadsController = new AmoCrmLeadsController();
        $clientsChange = [];

        foreach($array as $client) {
            $clientsChange[] = [
                'id' => $client['id'],
                'account_id' => $client['account_id'],
                'update' => false
            ];

            if($client['vk_id']) {
                if($contact = Contacts::where('vk_id', $client['vk_id'])->first()) {
                    $leadsIds = json_decode($contact['leads']);
                    $leads = $amoLeadsController->getLeadsByIds($leadsIds);

                    $checkLeads = $amoLeadsController->checkLeads($leads, $client['vk_group']);

                    if($checkLeads) {
                        $export = new Export();
                        $export->__set('action', 'update');
                        $export->__set('type', 'lead');
                        $export->__set('entity', json_encode(AmoCrmLeadsController::templateUpdateLead($client, $checkLeads, 'leads')));
                        $export->save();
                        // LogProvider::log("Редактировать сделку");
                    } else {
                        $export = new Export();
                        $export->__set('action', 'create');
                        $export->__set('type', 'lead');
                        $export->__set('entity', json_encode(AmoCrmLeadsController::templateCreateLead($client, $contact, 'leads')));
                        $export->save();
                        // LogProvider::log("Создать сделку");
                    }
                } else {
                    $export = new Export();
                    $export->__set('action', 'create');
                    $export->__set('type', 'all');
                    $export->__set('entity', json_encode(AmoCrmLeadsController::templateCreateContactAndLead($client, 'leads')));
                    $export->save();
                    // LogProvider::log("Создать контакт и сделку");
                }
            }
        }

        return $clientsChange;
    }

    public function checkOrdersFromBS(): void {
        $limit = 100;
        for($i=0;;$i++) {
            $orders = Orders::where('update', true)->limit($limit)->offset($limit * $i)->get()->toArray();
            if(sizeof($orders) <= 0) break;
            $orderChange = $this->sendToExport($orders);
            Orders::upsert($orderChange, ['id'], ['update' => false]);
        }
    }

    public function checkClientsFromBS(): void {
        $limit = 100;
        for($i=0;;$i++) {
            $clients = Clients::where('update', true)->limit($limit)->offset($limit * $i)->get()->toArray();
            if(sizeof($clients) <= 0) break;
            $clientsChange = $this->sendClientsToExport($clients);
            Clients::upsert($clientsChange, ['id'], ['update' => false]);
        }
    }

    protected function getClientsBySeconds($hour, $account, $date, $minute, $client_send, $client_send_date) {
        $seconds = [];
        for($i=0;$i<60;$i++) {
            $number = substr("0{$i}", -2);
            $number_two_i = $i + 1;
            $number_two = substr("0{$number_two_i}", -2);
            $seconds[] = ["{$minute}:{$number}", "{$minute}:{$number_two}"];
        }

        $size = 500;

        self::$login = $account['login'];
        self::$password = $account['password'];

        foreach($seconds as $sec) {
            for($i=0;;$i++) {
                $start = $i > 0 ? $i * $size : 0;
                $from = $date . " {$hour}:{$sec[0]}";
                $to = $date . " {$hour}:{$sec[1]}";

                $response = $this->getAndSetClients($from, $to, $size, $start);

                if(gettype($response) == 'object' && isset($response['count']) && $response['count'] > 0) {
                    $serialize_clients = self::serializeClient($response['customers'], $client_send, $client_send_date);
                    $this->setClients($serialize_clients);
                } else {
                    break;
                }
            }
        }

        return true;
    }

    protected function getClientsByMinutes($hour, $account, $date, $client_send, $client_send_date) {
        $minutes = [];
        for($i=0;$i<60;$i++) {
            $number = substr("0{$i}", -2);
            $minutes[] = ["{$number}:00", "{$number}:59"];
        }

        $size = 500;

        self::$login = $account['login'];
        self::$password = $account['password'];


        foreach($minutes as $minute) {
            for($i=0;;$i++) {
                $start = $i > 0 ? $i * $size : 0;
                $from = $date . " {$hour}:{$minute[0]}";
                $to = $date . " {$hour}:{$minute[1]}";
                try {

                    $response = $this->getAndSetClients($from, $to, $size, $start);

                    if(gettype($response) == 'object' && isset($response['count']) && $response['count'] > 0) {
                        if($response['notReturnedCount'] > 5000) {
                            $minute = explode(':', $minute[0]);
                            $this->getClientsBySeconds($hour, $account, $date, $minute[0], $client_send, $client_send_date);
                            break;
                        } else {
                            $serialize_clients = self::serializeClient($response['customers'], $client_send, $client_send_date);
                            $this->setClients($serialize_clients);
                        }
                    } else {
                        break;
                    }
                } catch (\Exception $e) {
                    LogProvider::log("Аккаунт: " . $account['login'] . ". Ошибка в минуте: " . $from);
                    break;
                }
            }
        }

        return true;
    }

    protected function getAndSetClients($from, $to, $size, $start) {
        $data = [
            'lastContactDateFrom' => $from,
            'lastContactDateTill' => $to,
            'pageSize' => $size,
            'startRowNumber' => $start,
        ];

        return $this->requestToBS('customers.get', $data);
    }

    public function getClients() {
        try {
            $dateFrom = date('o-m-d', strtotime('yesterday'));
            $client_send_date = date('d.m.o', strtotime('yesterday'));

            $size = 500;

            $accounts = BlueSalesAccountsController::getList();

            $hours = [ '00', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23' ];

            foreach($accounts as $account) {

                if($account['client_send'] == 0) continue;

                try {
                    self::$login = $account['login'];
                    self::$password = $account['password'];

                    foreach($hours as $hour) {

                        $from = $dateFrom . " {$hour}:00:00";
                        $to = $dateFrom . " {$hour}:59:59";

                        for($i=0;;$i++) {
                            $start = $i > 0 ? $i * $size : 0;

                            $response = $this->getAndSetClients($from, $to, $size, $start);

                            if(gettype($response) == 'object' && isset($response['count']) && $response['count'] > 0) {
                                if($response['notReturnedCount'] > 5000) {
                                    $this->getClientsByMinutes($hour, $account, $dateFrom, $account['client_send'], $client_send_date);
                                    break;
                                } else {
                                    $serialize_clients = self::serializeClient($response['customers'], $account['client_send'], $client_send_date);
                                    $this->setClients($serialize_clients);
                                }
                            } else {
                                break;
                            }
                        }

                    }

                    LogProvider::log("Закончен импорт из аккаунта: " . $account['login']);
                } catch (\Exception $e) {
                    LogProvider::log("Закончен импорт из аккаунта: " . $account['login'] . " с ошибкой: " . $e->getMessage());
                    continue;
                }
            }
        } catch (\Exception $e) {
            LogProvider::log("Ошибка: " . $e->getMessage());
        }
    }

    protected function setClients(array $clients): void {

        $ret = [];

        foreach($clients as $client) {
            $client_db = Clients::find($client['id']);
            if(
                $client_db &&
                $client_db['contact_name'] == $client['contact_name'] &&
                $client_db['contact_phone'] == $client['contact_phone'] &&
                $client_db['contact_email'] == $client['contact_email'] &&
                $client_db['contact_next_date'] == $client['contact_next_date'] &&
                $client_db['vk_id'] == $client['vk_id'] &&
                $client_db['vk_group'] == $client['vk_group'] &&
                $client_db['status_type'] == $client['status_type'] &&
                $client_db['tag'] == $client['tag'] &&
                $client_db['manager_full_name'] == $client['manager_full_name'] &&
                $client_db['tags'] == json_encode($client['tags'])
            ) {

            } else {
                $client['tags'] = json_encode($client['tags']);
                $client['update'] = true;
                $client['account_id'] = 1;
                $ret[] = $client;
            }
        }

        $clients_list = array_chunk($ret, 100);

        foreach($clients_list as $clients_list_items) {
            Clients::upsert($clients_list_items, [
                'id'
            ], [
                'contact_name',
                'contact_phone',
                'contact_email',
                'contact_next_date',
                'vk_id',
                'vk_group',
                'status_type',
                'tag',
                'manager_full_name',
                'tags',
                'update',
            ]);
        }
    }

    protected static function getYears(): array {
        $years = [];
        for($i=2022;$i<=date('o');$i++) $years[] = $i;
        return $years;
    }

    protected static function formatDate($day, $month, $year) {
        return "{$year}-" . mb_substr("0{$month}", -2) . "-" . mb_substr("0{$day}", -2);
    }

    protected static function getArrayFromBS() {
        $result_date = [];

        $stop = false;
        foreach(self::getYears() as $year) {
            for($month=1;$month<=12;$month++) {
                $countDays = date('t', mktime(0, 0, 0, $month, 1, $year));
                for($day=1;$day<=$countDays;$day++) {
                    $day_timestamp = mktime(0, 0, 0, $month, $day, $year);
                    $next_day_timestamp = strtotime('+1 day', $day_timestamp);

                    $result_date[] = [
                        'from' => self::formatDate($day, $month, $year),
                        'to' => date('o-m-d', $next_day_timestamp)
                    ];

                    if($year == date('o') && $month == date('n') && $day == date('j') - 1) {
                        $stop = true;
                        break;
                    }
                }
                if($stop) break;
            }
        }

        return $result_date;
    }

    /**
     * Получение заказов, начиная с 01.01.2020 по вчерашний день.
     * У BlueSales (далее BS) нет понятия как "дата изменения заказа", поэтому
     * приходится получать их все и сравнивать изменения уже непосредственно
     * с данными в БД.
     *
     * Помимо вышесказанного, если BS API передавать сразу интервал дат в
     * несколько лет, да или хотя бы недель, то каждый последующий ответ от
     * АПИ приходится ждать минимум несколько минут, а потом более. Поэтому используется дневной интервал.
     * Заказы за один день BS отдает с преемлемой скоростью, поэтому используется такой
     * некрасивый foreach и for, ибо иначе никак.
     *
     * @return int - Возвращает кол-во заказов, которые были добавлены или изменены в БД,
     * которые в последующем будут отправлены в АмоСрм
     */
    public function getOrdersYesterday() {
        // $dates_for_request = self::getArrayFromBS();

        // return $dates_for_request;

        $accounts = BlueSalesAccountsController::getList();
        $count = 0;

        $dates_for_request = [
            [
                'from' => date('o-m-d', strtotime('yesterday')),
                'to' => date('o-m-d')
            ]
        ];

        foreach($dates_for_request as $date) {
            $orders = $this->getOrdersPull($date['from'], $date['to']);

            $serialize_orders = self::serializeOrders($orders);

            $count = $count + sizeof($serialize_orders);

            $ret = [];

            foreach($serialize_orders as $order) {
                $order_db = Orders::find($order['id']);
                if(
                    $order_db &&
                    $order_db['contact_name'] == $order['contact_name'] &&
                    $order_db['contact_phone'] == $order['contact_phone'] &&
                    $order_db['contact_email'] == $order['contact_email'] &&
                    $order_db['contact_next_date'] == $order['contact_next_date'] &&
                    $order_db['vk_id'] == $order['vk_id'] &&
                    $order_db['vk_group'] == $order['vk_group'] &&
                    $order_db['status_type'] == $order['status_type'] &&
                    $order_db['tag'] == $order['tag'] &&
                    $order_db['manager_full_name'] == $order['manager_full_name'] &&
                    $order_db['date'] == $order['date'] &&
                    $order_db['tag_position_marking'] == $order['tag_position_marking'] &&
                    $order_db['tag_position_name'] == $order['tag_position_name'] &&
                    $order_db['price'] == $order['price'] &&
                    $order_db['tags'] == json_encode($order['tags'])
                ) {

                } else {
                    $order['tags'] = json_encode($order['tags']);
                    $order['update'] = true;
                    $order['account_id'] = 1;
                    $ret[] = $order;
                }
            }

            $orders = array_chunk($ret, 100);

            foreach($orders as $order_list) {
                Orders::upsert($order_list, [
                    'id'
                ], [
                    'contact_name',
                    'contact_phone',
                    'contact_email',
                    'contact_next_date',
                    'vk_id',
                    'vk_group',
                    'status_type',
                    'tag',
                    'manager_full_name',
                    'date',
                    'tag_position_marking',
                    'tag_position_name',
                    'price',
                    'tags',
                    'update',
                ]);
            }

//            foreach($accounts as $account) {
//                self::$login = $account['login'];
//                self::$password = $account['password'];
//
//                $orders = $this->getOrders($date['from'], $date['to']);
//
//
//            }
            LogProvider::log("BS Заказы. Дата: {$date['from']}");
        }

        return $count;
    }

    public function getAndRemoveClients() {
        $orders = Orders::all();

        $ids = [];

        foreach($orders as $order) {
            if($order['vk_id'] > 0) {
                if($client = Clients::where('vk_id', $order['vk_id'])->first())
                    $ids[] = $client['id'];
            }
        }

        Clients::destroy($ids);
    }

    /**
     * Функция для запроса данных у BlueSales.
     *
     * @param string $command
     * @param array $data
     * @return \Illuminate\Http\Client\Response
     */
    protected function requestToBS(string $command, array $data): \Illuminate\Http\Client\Response {
        return Http::post(self::$url . "Customers/WebServer.aspx?login=" . self::$login . "&password=" . self::$password . "&command={$command}", $data);
    }

    protected static function serializeClient(array $clients, int $client_send, string $client_send_date): array {
        $return = [];

        foreach($clients as $client) {

            $continue = true;

            if(isset($client['customFields']) && is_array($client['customFields'])) {
                foreach($client['customFields'] as $field) {
                    if($field['fieldId'] == $client_send && $field['value'] == $client_send_date) {
                        $continue = false;
                    }
                }
            }

            if($continue == true) continue;

            $element = self::getClientTemplate();

            $element['id'] = $client['id'];
            $element['contact_name'] = $client['fullName'] ?? null;
            $element['contact_phone'] = $client['phone'] ?? null;
            $element['contact_email'] = $client['email'] ?? null;
            $element['contact_next_date'] = $client['nextContactDate'] ?? null;

            if(isset($client['vk'])) {
                $element['vk_id'] = isset($client['vk']['id']) && (int) $client['vk']['id'] > 0 ? (int) $client['vk']['id'] : null;
                $element['vk_group'] = isset($client['vk']['messagesGroupId']) && (int) $client['vk']['messagesGroupId'] > 0 ? (int) $client['vk']['messagesGroupId'] : null;
            }

            if(isset($client['crmStatus'])) {
                $element['status_type'] = $client['crmStatus']['type'] ?? null;
            }

            if(isset($client['tags']) && is_array($client['tags']) && isset($client['tags'][0]) && isset($client['tags'][0]['name'])) {
                $element['tag'] = $client['tags'][0]['name'];
            }

            if(isset($client['manager'])) {
                $element['manager_full_name'] = mb_ereg_replace( "[^A-Za-zА-Яа-я\.\-]", '', $client['manager']['fullName']) ?? null;
            }

            if(isset($client['customFields']) && is_array($client['customFields'])) {
                foreach($client['customFields'] as $custom) {
                    if(isset($custom['valueAsText']) && $custom['valueAsText'] != '') {
                        if($custom['fieldId'] == 2406 || $custom['fieldId'] == 2405 || $custom['fieldId'] == 2392)
                            $element['tags'][] = $custom['valueAsText'];
                    }
                }
            }

            $return[] = $element;
        }

        return $return;
    }

    protected static function serializeOrders(array $orders): array {

        $return = [];

        foreach($orders as $order) {
            $element = self::getOrderTemplate();

            $element['id'] = $order['id'];

            if(isset($order['customer'])) {
                $element['contact_name'] = $order['customer']['fullName'] ?? null;
                $element['contact_phone'] = $order['customer']['phone'] ?? null;
                $element['contact_email'] = $order['customer']['email'] ?? null;
                $element['contact_next_date'] = $order['customer']['nextContactDate'] ?? null;

                if(isset($order['customer']['vk'])) {
                    $element['vk_id'] = isset($order['customer']['vk']['id']) && (int) $order['customer']['vk']['id'] > 0 ? (int) $order['customer']['vk']['id'] : null;
                    $element['vk_group'] = isset($order['customer']['vk']['messagesGroupId']) && (int) $order['customer']['vk']['messagesGroupId'] > 0 ? (int) $order['customer']['vk']['messagesGroupId'] : null;
                }

                if(isset($order['customer']['crmStatus'])) {
                    $element['status_type'] = $order['customer']['crmStatus']['type'] ?? null;
                }

                if(isset($order['customer']['tags']) && is_array($order['customer']['tags']) && isset($order['customer']['tags'][0]) && isset($order['customer']['tags'][0]['name'])) {
                    $element['tag'] = $order['customer']['tags'][0]['name'];
                }

                if(isset($order['customer']['manager'])) {
                    $element['manager_full_name'] = mb_ereg_replace( "[^A-Za-zА-Яа-я\.\-]", '', $order['customer']['manager']['fullName']) ?? null;
                }

                if(isset($order['customer']['customFields']) && is_array($order['customer']['customFields'])) {
                    foreach($order['customer']['customFields'] as $custom) {
                        if(isset($custom['valueAsText']) && $custom['valueAsText'] != '') {
                            if($custom['fieldId'] == 2406 || $custom['fieldId'] == 2405 || $custom['fieldId'] == 2392)
                                $element['tags'][] = $custom['valueAsText'];
                        }
                    }
                }
            }

            $element['date'] = $order['date'] ?? null;

            if(isset($order['goodsPositions']) && is_array($order['goodsPositions']) && isset($order['goodsPositions'][0])) {
                if(isset($order['goodsPositions'][0]['goods'])) {
                    $element['tag_position_marking'] = $order['goodsPositions'][0]['goods']['marking'] ?? null;
                    $element['tag_position_name'] = $order['goodsPositions'][0]['goods']['name'] ?? null;
                }
            }

            $element['price'] = $order['totalSumWithoutDiscount'] ?? null;

            $return[] = $element;
        }

        return $return;
    }

    protected static function getClientTemplate(): array {
        return [
            'id' => null,
            'contact_name' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'contact_next_date' => null,
            'vk_id' => null,
            'vk_group' => null,
            'status_type' => null,
            'tag' => null,
            'manager_full_name' => null,
            'tags' => [],
        ];
    }

    protected static function getOrderTemplate(): array {
        return [
            'id' => null,
            'contact_name' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'contact_next_date' => null,
            'vk_id' => null,
            'vk_group' => null,
            'status_type' => null,
            'tag' => null,
            'manager_full_name' => null,
            'date' => null,
            'tag_position_marking' => null,
            'tag_position_name' => null,
            'price' => null,
            'tags' => [],
        ];
    }

    protected function pushData(Pool $pool, $account, $data) {
        return $pool->post(self::$url . "Customers/WebServer.aspx?login=" . $account['login'] . "&password=" . $account['password'] . "&command=orders.get", $data);
    }

    protected function getOrdersPull(string $dateFrom, string $dateTo): array {
        $orders = [];

        $size = 300;
        $accounts = BlueSalesAccountsController::getList();

        for($i=0;;$i++) {
            $start = $i > 0 ? $i * $size : 0;
            $data = [
                'dateFrom' => $dateFrom,
                'dateTill' => $dateTo,
                'pageSize' => $size,
                'startRowNumber' => $start,
            ];

            $responses = Http::pool(fn (Pool $pool) => $accounts->map(fn ($account) => $this->pushData($pool, $account, $data)));

            $break = true;
            foreach($responses as $response) {
                try {
                    if(gettype($response) == 'object') {
                        if($response instanceof ConnectException) {
                            // LogProvider::log("Нет элементов");
                        } else {
                            if(isset($response['count']) && $response['count'] > 0) {
                                $break = false;
                                $orders = array_merge($orders, $response['orders']);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    LogProvider::log("Какая то ошибка: " . $e->getMessage());
                }
            }

            if($break == true) break;
        }

        return $orders;
    }

    protected function getOrders(string $dateFrom, string $dateTo): array {

        $size = 300;
        $orders = [];

        for($i=0;;$i++) {
            $start = $i > 0 ? $i * $size : 0;
            $data = [
                'dateFrom' => $dateFrom,
                'dateTill' => $dateTo,
                'pageSize' => $size,
                'startRowNumber' => $start,
            ];
            $response = $this->requestToBS('orders.get', $data);

            if(gettype($response) == 'object' && isset($response['count']) && $response['count'] > 0) {
                $orders = array_merge($orders, $response['orders']);
            } else {
                break;
            }
        }

        return $orders;
    }

}
