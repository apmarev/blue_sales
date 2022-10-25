<?php

namespace App\Http\Controllers\BlueSales;

use App\Http\Controllers\Controller;

use App\Models\Accounts;

use Illuminate\Http\Request;

class BlueSalesAccountsController extends Controller {

    public function create(Request $request): Accounts {
        $login = $request->get('login');
        $password = $request->get('password');

        if($account = Accounts::where('login', $login)->where('password', $password)->first()) {
            return $account;
        } else {
            $account = new Accounts();
            $account->__set('login', $login);
            $account->__set('password', $password);
            $account->save();
            return $account;
        }
    }

    public static function getList(): \Illuminate\Database\Eloquent\Collection {
        return Accounts::all();
    }

}
