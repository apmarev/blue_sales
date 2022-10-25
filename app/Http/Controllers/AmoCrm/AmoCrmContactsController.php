<?php

namespace App\Http\Controllers\AmoCrm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\AmoCrm\AmoCrmAuthController;
use App\Models\Contacts;
use App\Providers\LogProvider;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class AmoCrmContactsController extends Controller {

    public function contactsPull(): string {

        Contacts::truncate();

        $controller = new AmoCrmAuthController();
        $sizePull = 5;

        for($i=1;;$i=$i+$sizePull) {

            $break = false;

            $ret = [];

            for($t=$i;$t<$i+$sizePull;$t++) {
                $ret[] = "/contacts?limit=250&page={$t}&with=leads";
            }

            $responses = $controller->get_request_pull($ret);

            foreach($responses as $response) {
                if($response instanceof \GuzzleHttp\Exception\ConnectException) {
                    $break = true;
                    break;
                } else {
                    $result = $this->serialize($response);

                    if(!$result) {
                        $break = true;
                        break;
                    }
                }
            }

            if($break == true) break;
        }

        LogProvider::log("Завершен импорт контактов из AmoCRM");
        return "Success";
    }

    /**
     * Импорт всех контактов из АмоСрм в БД
     *
     * @return string
     */
    public function contacts(): string {
        $controller = new AmoCrmAuthController();

        for($i=1;;$i++) {
            $response = $this->serialize($controller->get_request("/contacts?limit=250&page={$i}&with=leads"));

            if(!$response) {
                LogProvider::log("Завершен импорт контактов из AmoCRM");
                break;
            }
            if($i % 50 == 0) {
                $count = $i * 250;
                LogProvider::log("Контакты. Страница {$i}. Кол-во обработанных сущностей: {$count}");
            }
        }

        return "Ok";
    }

    /**
     * Получение и сохранение контактов из АмоСрм,
     * которые были изменены вчера
     *
     * @return string
     */
    public function contactsYesterday(): string {
        $controller = new AmoCrmAuthController();

        $from = '';
        $to = '';

        for($i=1;;$i++) {
            $response = $this->serialize($controller->get_request("/contacts?limit=250&page={$i}&filter[updated_at][from]={$from}&filter[updated_at][to]={$to}&with=leads"));
            if(!$response) {
                LogProvider::log("Завершен импорт измененных вчера контактов из AmoCRM");
                break;
            }
        }

        return "Ok";
    }

    /**
     * Сериализация массива контактов к формату,
     * требуемому для сохранения контактов в БД
     *
     * @param $result - Ответ от АПИ АмоСрм при запросе списка контактов
     * @return bool
     */
    protected function serialize($result): bool {
        try {
            if(isset($result['_embedded']) && isset($result['_embedded']['contacts']) && is_array($result['_embedded']['contacts'])) {
                if(sizeof($result['_embedded']['contacts']) > 0) {

                    $elements = [];

                    foreach($result['_embedded']['contacts'] as $contact) {
                        $el = [
                            'id' => $contact['id'],
                            'update' => $contact['updated_at'],
                            'name' => $contact['first_name'],
                            'phones' => [],
                            'emails' => [],
                            'vk_id' => null,
                            'leads' => []
                        ];

                        if(isset($contact['custom_fields_values'])) {
                            foreach($contact['custom_fields_values'] as $custom) {
                                if($custom['field_id'] == 176801) foreach($custom['values'] as $value) {
                                    $el['phones'][] = preg_replace("/[^,.0-9]/", '', $value['value']);
                                }
                                if($custom['field_id'] == 176803) foreach($custom['values'] as $value) $el['emails'][] = $value['value'];
                                if($custom['field_id'] == 708615) {
                                    if($custom['values'][0]['value'] != '') {
                                        $vk_id = preg_replace("/[^0-9]/", '', $custom['values'][0]['value']);
                                        $el['vk_id'] = $vk_id > 0 && strlen($vk_id) <= 10 ? $vk_id : null;
                                    }
                                }
                            }
                        }

                        if(isset($contact['_embedded']) && isset($contact['_embedded']['leads']) && is_array($contact['_embedded']['leads']) && sizeof($contact['_embedded']['leads']) > 0) {
                            foreach($contact['_embedded']['leads'] as $lead)
                                $el['leads'][] = $lead['id'];
                        }

                        $el['phones'] = json_encode($el['phones']);
                        $el['emails'] = json_encode($el['emails']);
                        $el['leads'] = json_encode($el['leads']);

                        $elements[] = $el;
                    }

                    return self::upsertContacts($elements);
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (\Exception $e) {
            LogProvider::log("Ошибка импорта контактов из амо: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Сохранение контактов в БД
     *
     * @param array $contacts - Массив сериализованных объектов контактов
     * @return bool
     */
    protected static function upsertContacts(array $contacts): bool {
        try {
            $contacts = array_chunk($contacts, 250);

            foreach($contacts as $list) {
                try {
                    Contacts::upsert($list, [
                        'id'
                    ], [
                        'update',
                        'name',
                        'phones',
                        'emails',
                        'vk_id',
                        'leads',
                    ]);
                } catch (\Exception $e) {
                    throw new \Exception($e);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
