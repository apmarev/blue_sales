<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('update', function (Blueprint $table) {
            $table->id();

            $table->string('action')->comment('Возможные действия: create, update');
            $table->string('type')->comment('Возможные типы: lead, contact, all');
            $table->longText('entity')->comment('JSON объектов для отправки в амо. Может быть как массивом, так и объектом');
            $table->boolean('exported')->default(false);

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('update');
    }
};
