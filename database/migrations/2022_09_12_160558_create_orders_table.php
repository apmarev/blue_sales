<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_next_date')->nullable();
            $table->bigInteger('vk_id')->nullable();
            $table->bigInteger('vk_group')->nullable();
            $table->integer('status_type')->nullable();
            $table->string('tag')->nullable();
            $table->string('manager_full_name')->nullable();
            $table->string('date')->nullable();
            $table->string('tag_position_marking')->nullable();
            $table->string('tag_position_name')->nullable();
            $table->integer('price')->nullable();
            $table->text('tags')->nullable();

            $table->boolean('update')->default(true);

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('orders');
    }
};
