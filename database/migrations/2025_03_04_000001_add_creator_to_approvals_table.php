<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table(table: 'approvals', callback: function (Blueprint $table) {
            $table->after(column: 'original_data', callback: fn(Blueprint $table): null => $table->nullableMorphs(name: 'creator'));
        });
    }

    public function down(): void
    {
        Schema::table(table: 'approvals', callback: function (Blueprint $table) {
            $table->dropMorphs(name: 'creator');
        });
    }
};
