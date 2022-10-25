<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::table('accounts', function (Blueprint $table) {
            $table->integer('client_send')->comment('ID костомного поля Клиент писал')->default(0);
        });
    }

    public function down() {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('client_send');
        });
    }
};
