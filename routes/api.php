<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BlueSales\BlueSalesController;
use App\Http\Controllers\BlueSales\BlueSalesAccountsController;

use App\Http\Controllers\AmoCrm\AmoCrmSerializeController;
use App\Http\Controllers\AmoCrm\AmoCrmAuthController;
use App\Http\Controllers\AmoCrm\AmoCrmContactsController;
use App\Http\Controllers\Import\ImportController;

Route::prefix('amo')->group(function() {
    Route::prefix('access')->group(function() {
        Route::post('/key', [AmoCrmAuthController::class, 'key']);
    });

    Route::get('contacts', [AmoCrmContactsController::class, 'contacts']);
    Route::get('contacts-pull', [AmoCrmContactsController::class, 'contactsPull']);
});

Route::post('/account', [BlueSalesAccountsController::class, 'create']);

Route::get('/amo/orders', [AmoCrmSerializeController::class, 'orders']);

Route::get('/orders', [BlueSalesController::class, 'getOrdersYesterday']);
Route::get('/clients', [BlueSalesController::class, 'getClients']);

Route::post('/orders', [BlueSalesController::class, 'checkOrdersFromBS']);

Route::get('/tests', [ImportController::class, 'get']);
