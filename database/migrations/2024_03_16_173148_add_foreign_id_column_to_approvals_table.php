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
            $table->unsignedBigInteger('foreign_key')->nullable()->after('original_data');
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table): void {
            $table->dropColumn('foreign_key');
        });
    }
};
