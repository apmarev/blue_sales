<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('vk_id')->change();
        });
    }

    public function down() {
        Schema::table('contacts', function (Blueprint $table) {
            $table->bigInteger('vk_id')->change();
        });
    }
};
