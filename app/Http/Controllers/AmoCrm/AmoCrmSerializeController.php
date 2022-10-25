<?php

namespace App\Http\Controllers\AmoCrm;

use App\Http\Controllers\Controller;
use App\Models\Orders;

class AmoCrmSerializeController extends Controller {

    protected static function getStatusType($status): array {
        $ret = [
            'status' => 143,
            'field_id' => null,
            'field_value' => null
        ];

        if($status) {
            switch ($status) {
                case 0:
                    $ret = [
                        'status' => 143,
                        'field_id' => null,
                        'field_value' => 'Холодная воронка'
                    ];
                    break;
                case 1:
                    $ret = [
                        'status' => 143,
                        'field_id' => null,
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
            }
        }

        return $ret;
    }

    protected static function order_serialize(Orders $order) {
        $order['status'] = self::getStatusType($order['status_type']);

        $tags = [];
        if($order['tag'] != '') $tags[] = $order['tag'];
        if($order['tag_position_marking'] != '') $tags[] = $order['tag_position_marking'];
        if($order['tag_position_name'] != '') $tags[] = $order['tag_position_name'];
        if($order['tags'] != '') {
            $tags_array = json_decode($order['tags'], true);
            if(is_array($tags_array) && sizeof($tags_array) > 0) {
                foreach($tags_array as $t) $tags[] = $t;
            }
        }

        $order['tags'] = $tags;

        return $order;
    }

    public function orders() {
        $orders = Orders::where('update', true)->get();
        $size = [];

        foreach($orders as $order) {
            $serialize = self::order_serialize($order);
            $size[] = $serialize;
        }

        return [
            'count' => sizeof($size),
            'items' => $size
        ];
    }

}
