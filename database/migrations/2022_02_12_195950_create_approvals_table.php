<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvalable');
            $table->enum('state', [0, 1, 2])->default(0)->comment('0:PENDING, 1:APPROVED, 2:REJECTED');
            $table->json('new_data')->nullable();
            $table->json('original_data')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('approvals');
    }
};
