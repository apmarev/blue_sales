<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            $table->integer('update');
            $table->string('name')->nullable();
            $table->text('phones')->nullable();
            $table->text('emails')->nullable();
            $table->integer('vk_id')->nullable();
            $table->text('leads')->nullable();

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('contacts');
    }
};
