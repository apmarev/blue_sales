<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration  {

    public function up() {
        Schema::create('access', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->integer('expires_in');

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('access');
    }
};
