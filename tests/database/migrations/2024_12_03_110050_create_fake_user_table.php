<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('fake_users', callback: function (Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string(column: 'name');
            $table->string(column: 'email')->unique();
            $table->string('password');
        });
    }
};
