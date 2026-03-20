<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table(table: 'approvals', callback: function (Blueprint $table): void {
            $table->after(column: 'original_data', callback: fn (Blueprint $table): null => $table->nullableMorphs(name: 'creator'));
        });
    }

    public function down(): void
    {
        Schema::table(table: 'approvals', callback: function (Blueprint $table): void {
            $table->dropMorphs(name: 'creator');
        });
    }
};
