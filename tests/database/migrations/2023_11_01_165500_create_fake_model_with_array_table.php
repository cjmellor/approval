<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('fake_models_with_array', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('meta')->nullable();

            $table->json('data')->nullable();
        });
    }
};
