<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\AmoCrm\AmoCrmContactsController;
use App\Http\Controllers\AmoCrm\AmoCrmLeadsController;
use App\Http\Controllers\Controller;
use App\Models\Contacts;
use App\Models\Export;
use App\Providers\LogProvider;

class ImportController extends Controller {

    protected static function getFieldId(int $index): ?int {

        $return = null;

        switch($index) {
            case 3:
                $return = 710697;
                break;
            case 4:
                $return = 278351;
                break;
            case 5:
                $return = 703383;
                break;
            case 6:
                $return = 703385;
                break;
            case 7:
                $return = 278339;
                break;
            case 8:
                $return = 278321;
                break;
            case 9:
                $return = 278323;
                break;
            case 10:
                $return = 278325;
                break;
        }

        return $return;
    }

    public function get() {
        $elements = $this->parseCsv();

        $items = [];

        $i = 0;
        foreach($elements as $element) {
            if($element) {
                $el = [
                    'name' => $element[0],
                    'contact_name' => $element[1],
                    'contact_phone' => preg_replace("/[^,.0-9]/", '', $element[2]),
                    'custom' => []
                ];

                for($c=3;$c<=10;$c++) {
                    if(isset($element[$c]) && $element[$c] != '') {
                        $el['custom'][] = [
                            'field_id' => self::getFieldId($c),
                            'values' => [
                                [ 'value' => $element[$c] ]
                            ],
                        ];
                    }
                }

                $items[] = $el;
            }

            $i++;
        }

        return $this->getLeads($items);
    }

    protected function getLeads($items) {

        $controller = new AmoCrmLeadsController();

        $count = 0;
        foreach($items as $item) {
            if($contact = Contacts::where('phones', 'like', "%{$item['contact_phone']}%")->first()) {
                $leads = json_decode($contact['leads']);
                $leads_list = $controller->getLeadsByIds($leads);

                $isset = false;

                if(sizeof($leads_list) > 0) {
                    foreach($leads_list as $lead) {
                        if(isset($lead['_embedded']) && isset($lead['_embedded']['tags']) && is_array($lead['_embedded']['tags'])) {
                            foreach($lead['_embedded']['tags'] as $tag) {
                                if(isset($tag['name']) && $tag['name'] == 'Пролог') {
                                    $isset = true;
                                }
                            }
                        }
                    }
                }
                if(!$isset) {
                    $lead_data = [
                        'pipeline_id' => 3493222,
                        'responsible_user_id' => 7542007,
                        'custom_fields_values' => $item['custom'],
                        '_embedded' => [
                            'tags' => [
                                [ 'name' => 'Пролог' ],
                            ],
                            'contacts' => [
                                [ 'id' => $contact['id'] ],
                            ],
                        ],
                    ];

                    $export = new Export();
                    $export->__set('action', 'create');
                    $export->__set('type', 'lead');
                    $export->__set('entity', json_encode($lead_data));
                    $export->save();
                }
            }


            $count++;

            if($count % 300 == 0) {
                LogProvider::log("Завершен " . $count);
            }
        }

    }

    public function parseCsv() {
        $line_of_text = [];
        $file_handle = fopen(__DIR__ . '/file.csv', 'r');

        while (!feof($file_handle)) {
            $line_of_text[] = fgetcsv($file_handle, 0, ';');
        }
        fclose($file_handle);
        return $line_of_text;
    }

}
