<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up()
    {
        Schema::create('fake_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('meta');
        });

        Schema::create('fake_model_with_includes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('meta')->nullable();
            $table->string('excluded_field');
        });
    }
};
