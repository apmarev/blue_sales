<?php

namespace App\Http\Controllers\AmoCrm;

use App\Http\Controllers\Controller;
use App\Models\Export;

class AmoCrmSendController extends Controller {

    public static int $count = 50;

    public function checkCustomValuesIssetLeads($leads) {

        $field_id = 712087;

        $controller = new AmoCrmAuthController();

        $managers = [];

        foreach($leads as $lead) {
            if(isset($lead['custom_fields_values'])) {
                foreach($lead['custom_fields_values'] as $custom) {

                    if($custom['field_id'] == $field_id) {
                        $managers[] = mb_ereg_replace( "[^A-Za-zА-Яа-я\.\-]", '', $custom['values'][0]['value']);
                    }

                }
            }
        }

        $managers = array_unique($managers);

        $fields = $controller->get_request("/leads/custom_fields/{$field_id}");

        if(isset($fields['enums']) && is_array($fields['enums'])) {
            foreach($fields['enums'] as $enum) {
                foreach($managers as $key => $manager) {
                    if($enum['value'] == $manager) {
                        unset($managers[$key]);
                    }
                }
            }

            foreach($fields['enums'] as $enum) {
                $managers[] = $enum['value'];
            }
        }

        $enums = [];
        $managers = array_unique($managers);
        foreach($managers as $manager) {
            $enums[] = [
                'value' => $manager
            ];
        }

        if(sizeof($managers) > 0) {
            $controller->patch_request("/leads/custom_fields/{$field_id}", [
                'enums' => $enums
            ]);
        }


    }

    public function getCreateLeads() {

        $controller = new AmoCrmLeadsController();

        for($i=0;;$i++) {
            $leads = Export::where('type', 'lead')->where('action', 'create')->limit(self::$count)->get()->toArray();

            if(sizeof($leads) > 0) {
                $leadsIds = [];
                $create = [];
                foreach($leads as $lead) {
                    $create[] = json_decode($lead['entity'], true);
                    $leadsIds[] = $lead['id'];
                }

                $this->checkCustomValuesIssetLeads($create);

                $controller->createLeads($create);

                if(sizeof($leadsIds) > 0) Export::destroy($leadsIds);
            } else {
                break;
            }
        }

    }

    public function getUpdateLeads() {

        $controller = new AmoCrmLeadsController();

        for($i=0;;$i++) {
            $leads = Export::where('type', 'lead')->where('action', 'update')->limit(self::$count)->get()->toArray();

            if(sizeof($leads) > 0) {
                $leadsIds = [];
                $create = [];
                foreach($leads as $lead) {
                    $create[] = json_decode($lead['entity'], true);
                    $leadsIds[] = $lead['id'];
                }

                $this->checkCustomValuesIssetLeads($create);

                $controller->updateLeads($create);

                if(sizeof($leadsIds) > 0) Export::destroy($leadsIds);
            } else {
                break;
            }
        }

    }

    public function getCreateLeadsAndContacts() {
        $controller = new AmoCrmLeadsController();

        for($i=0;;$i++) {
            $leads = Export::where('type', 'all')->where('action', 'create')->limit(self::$count)->get()->toArray();

            if(sizeof($leads) > 0) {
                $leadsIds = [];
                $create = [];
                foreach($leads as $lead) {
                    $create[] = json_decode($lead['entity'], true);
                    $leadsIds[] = $lead['id'];
                }

                $this->checkCustomValuesIssetLeads($create);

                $controller->createContactsAndLeads($create);

                if(sizeof($leadsIds) > 0) Export::destroy($leadsIds);
            } else {
                break;
            }
        }
    }

}
