<?php

namespace App\Http\Controllers\AmoCrm;

use App\Http\Controllers\Controller;
use App\Providers\LogProvider;

class AmoCrmLeadsController extends Controller {

    public function checkLeads($leads, $vk_group_id) {

        foreach($leads as $lead) {
            $check = $this->checkLead($lead, $vk_group_id);
            if($check) {
                return $check;
            }
        }

        return null;
    }

    public function createLeads($leads) {
        $controller = new AmoCrmAuthController();
        return $controller->post_request('/leads', $leads);
    }

    public function updateLeads($leads) {
        $controller = new AmoCrmAuthController();
        return $controller->patch_request('/leads', $leads);
    }

    public function createContacts($contacts) {
        $controller = new AmoCrmAuthController();
        return $controller->post_request('/contacts', $contacts);
    }

    public function createContactsAndLeads($contactsAndLeads) {
        $controller = new AmoCrmAuthController();
        return $controller->post_request('/leads/complex', $contactsAndLeads);
    }

    public function checkLead($lead, $vk_group_id) {

        if(self::checkUntrackedStatusLead($lead) == false) return false;

        $checkTagsLead = self::checkTagsLead($lead, $vk_group_id);

        if($checkTagsLead) {
            return $checkTagsLead;
        }

        return null;

    }

    /**
     * Получение сделок по массиву ID сделок
     *
     * @param array $leadsIds - Массив целочисленных ID сделок
     * @return array - Массив объектов сделок
     */
    public function getLeadsByIds(array $leadsIds) {
        $result = [];

        $leads_list = array_chunk($leadsIds, 50);

        $controller = new AmoCrmAuthController();

        foreach($leads_list as $list) {

            $filter = "";
            $i = 0;
            foreach($list as $element) {
                $filter .= "filter[id][{$i}]={$element}&";
                $i++;
            }

            $response = $controller->get_request("/leads?{$filter}");

            if(isset($response['_embedded']) && isset($response['_embedded']['leads']) && is_array($response['_embedded']['leads']) && sizeof($response['_embedded']['leads']) > 0) {
                $result = array_merge($result, $response['_embedded']['leads']);
            }
        }

        return $result;
    }

    protected static function checkTagsLead($lead, $vk_group_id) {

        if($searchTag = self::getTagByNumber('key', $vk_group_id)) {
            if(isset($lead['_embedded']) && isset($lead['_embedded']['tags'])) {
                $tags = $lead['_embedded']['tags'];


                foreach($tags as $tag) {
                    if($searchTag == $tag['name'])
                        return $lead;
                }
            }
        }

        return null;

    }

    protected static function getStatusByType($status): array {
        $ret = [
            'status' => 143,
        ];

        if($status) {
            switch ($status) {
                case 0:
                    $ret = [
                        'status' => 143,
                        'field_id' => 702227,
                        'field_value' => 'Холодная воронка'
                    ];
                    break;
                case 1:
                    $ret = [
                        'status' => 143,
                        'field_id' => 702227,
                        'field_value' => 'Не целевой'
                    ];
                    break;
                case 2:
                    $ret = [
                        'status' => 47456056,
                    ];
                    break;
                case 3:
                    $ret = [
                        'status' => 47456059,
                    ];
                    break;
                case 4:
                    $ret = [
                        'status' => 142,
                    ];
                    break;
                case 5:
                    $ret = [
                        'status' => 143,
                        'field_id' => 702227,
                        'field_value' => 'Отмена заказа BS'
                    ];
                    break;
            }
        }

        return $ret;
    }

    protected static function addOtherFieldToLead($lead_data, $data, $getStatusByType) {

        if(isset($getStatusByType['field_id'])) {
            $lead_data['custom_fields_values'][] = [
                'field_id' => $getStatusByType['field_id'],
                'values' => [ [ 'value' => $getStatusByType['field_value'] ] ],
            ];
        }

        if($data['contact_next_date'] != '')
            $lead_data['custom_fields_values'][] = [
                'field_id' => 711751,
                'values' => [ [ 'value' => strtotime($data['contact_next_date']) ] ],
            ];

        if($data['manager_full_name'] != '')
            $lead_data['custom_fields_values'][] = [
                'field_id' => 712087,
                'values' => [ [ 'value' => $data['manager_full_name'] ] ],
            ];

        if($data['tag'] != '' && $data['tag'] != null)
            $lead_data['_embedded']['tags'][] = [ 'name' => $data['tag'] ];

        if(isset($data['tag_position_marking']) && $data['tag_position_marking'] != '' && $data['tag_position_marking'] != null)
            $lead_data['_embedded']['tags'][] = [ 'name' => $data['tag_position_marking'] ];

        $data['tags'] = json_decode($data['tags']);
        if(sizeof($data['tags']) > 0)
            foreach($data['tags'] as $tag)
                if($tag != null)
                    $lead_data['_embedded']['tags'][] = [ 'name' => $tag ];

        return $lead_data;
    }

    public static function templateUpdateLead($data, $lead, $type = 'order') {
        $getStatusByType = self::getStatusByType($data['status_type']);

        $lead_data = [
            'id' => $lead['id'],
            'status_id' => $getStatusByType['status'],
            'pipeline_id' => 5322871,
            'responsible_user_id' => 7542007,
            'custom_fields_values' => [],
            '_embedded' => [
                'tags' => [],
            ],
        ];

        if($type == 'order') {
            $lead_data['price'] = $data['price'];
        }

        $lead_data = self::addOtherFieldToLead($lead_data, $data, $getStatusByType);

        if(isset($lead['_embedded']) && isset($lead['_embedded']['tags'])) {
            foreach($lead['_embedded']['tags'] as $tag) {
                $lead_data['_embedded']['tags'][] = [ 'name' => $tag['name'] ];
            }
        }

        return $lead_data;
    }

    public static function templateCreateLead($data, $contact, $type = 'order') {

        $getStatusByType = self::getStatusByType($data['status_type']);

        $lead_data = [
            'status_id' => $getStatusByType['status'],
            'pipeline_id' => 5322871,
            'responsible_user_id' => 7542007,
            'custom_fields_values' => [],
            '_embedded' => [
                'tags' => [],
                'contacts' => [
                    [ 'id' => $contact['id'] ],
                ],
            ],
        ];

        if($type == 'order') {
            $lead_data['price'] = $data['price'];
        }

        $lead_data = self::addOtherFieldToLead($lead_data, $data, $getStatusByType);

        $tag_vk_group = self::getTagByNumber('key', $data['vk_group']);
        if($tag_vk_group)
            $lead_data['_embedded']['tags'][] = [
                'name' => $tag_vk_group
            ];

        return $lead_data;
    }

    public static function templateCreateContactAndLead($data, $type = 'order') {

        $contact = [
            'name' => $data['contact_name'],
            'first_name' => $data['contact_name'],
            'responsible_user_id' => 7542007,
            'custom_fields_values' => [
                [
                    'field_id' => 708615,
                    'values' => [ [ 'value' => "id{$data['vk_id']}" ] ],
                ],
            ],
        ];

        if($data['contact_phone'] != '') {
            $contact['custom_fields_values'][] = [
                'field_id' => 176801,
                'values' => [ [ 'value' => $data['contact_phone'] ] ],
            ];
        }

        if($data['contact_email'] != '') {
            $contact['custom_fields_values'][] = [
                'field_id' => 176803,
                'values' => [ [ 'value' => $data['contact_email'] ] ],
            ];
        }

        $getStatusByType = self::getStatusByType($data['status_type']);

        $lead = [
            'status_id' => $getStatusByType['status'],
            'pipeline_id' => 5322871,
            'responsible_user_id' => 7542007,
            'custom_fields_values' => [],
            '_embedded' => [
                'tags' => [],
                'contacts' => [ $contact ],
            ],
        ];

        $tag_vk_group = self::getTagByNumber('key', $data['vk_group']);
        if($tag_vk_group)
            $lead['_embedded']['tags'][] = [
                'name' => $tag_vk_group
            ];

        if($type == 'order') {
            $lead['price'] = $data['price'];
        }

        $lead = self::addOtherFieldToLead($lead, $data, $getStatusByType);

        return $lead;

    }

    protected static function checkUntrackedStatusLead($lead): bool {
        $untrackedStatuses = [ 142 ];

        $searchInUntrackedStatuses = array_search($lead['status_id'], $untrackedStatuses);
        if($searchInUntrackedStatuses && $searchInUntrackedStatuses >= 0) return false;

        return true;
    }

    protected static function getTagByNumber(string $type, $search) {
        $array = [
            'Русский язык ЕГЭ ВК vkontakte' => 137331585,
            'Базовая математика ЕГЭ vkontakte' => 143084342,
            'Математика ЕГЭ vkontakte' => 135803480,
            'Литература ЕГЭ vkontakte' => 151441700,
            'История ЕГЭ vkontakte' => 137331378,
            'Обществознание ЕГЭ ВК vkontakte' => 137331702,
            'Биология ЕГЭ ВК vkontakte' => 137331446,
            'География ЕГЭ ВК vkontakte' => 168452327,
            'Информатика ЕГЭ ВК vkontakte' => 137331920,
            'Физика ЕГЭ ВК vkontakte' => 99797563,
            'Химия ЕГЭ ВК vkontakte' => 137332003,
            'Английский язык ЕГЭ ВК vkontakte' => 137331795,
            'Немецкий язык ЕГЭ ВК vkontakte' => 168456080,
            'Химия 10 класс ВК vkontakte' => 197343744,
            'Английский язык 10 класс ВК vkontakte' => 197343798,
            'Математика 10 класс ВК vkontakte' => 197366397,
            'Биология 10 класс ВК vkontakte' => 197343788,
            'История 10 класс ВК vkontakte' => 198276645,
            'Русский язык 10 класс ВК vkontakte' => 198276627,
            'Обществознание 10 класс ВК vkontakte' => 197343806,
            'Физика 10 класс ВК vkontakte' => 197343818,
            'Литература 10 класс ВК vkontakte' => 197343827,
            'Русский язык ОГЭ ВК vkontakte' => 168455375,
            'Математика ОГЭ ВК vkontakte' => 168456727,
            'Литература ОГЭ ВК vkontakte' => 168455533,
            'Физика ОГЭ ВК vkontakte' => 168455361,
            'Биология ОГЭ ВК vkontakte' => 168455430,
            'Химия ОГЭ ВК vkontakte' => 168455444,
            'Обществознание ОГЭ ВК vkontakte' => 168455409,
            'География ОГЭ ВК vkontakte' => 168455550,
            'История ОГЭ ВК vkontakte' => 168455415,
            'Информатика ОГЭ ВК vkontakte' => 168455540,
            'Английский язык ОГЭ ВК vkontakte' => 168455391,
            'Умскул ВК vkontakte' => 124303372
        ];

        if($type == 'key') {
            foreach($array as $key => $value)
                if($value == $search) return $key;
        } else if ($type == 'value') {
            foreach($array as $key => $value)
                if($key == $search) return $value;
        }

        return null;
    }

}
