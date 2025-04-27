<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table): void {
            $table->timestamp(column: 'rolled_back_at')->nullable()->after('original_data');
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table): void {
            $table->dropColumn(columns: 'rolled_back_at');
        });
    }
};
