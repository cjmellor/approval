<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs(config(key: 'approval.approval.approval_pivot'));
            $table->enum('state', [0, 1, 2])->default(0)->comment('0:PENDING, 1:APPROVED, 2:REJECTED');
            $table->json(config(key: 'approval.approval.new_data'))->nullable();
            $table->json(config(key: 'approval.approval.original_data'))->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('approvals');
    }
};
