<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('account_id')->comment('ID акканута в таблице accounts');
        });
    }

    public function down() {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('account_id');
        });
    }
};
