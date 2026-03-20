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
            $table->string('custom_state')->nullable()->after('state');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('approvals', 'custom_state')) {
            Schema::table('approvals', function (Blueprint $table): void {
                $table->dropColumn('custom_state');
            });
        }
    }
};
