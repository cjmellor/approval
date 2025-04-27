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
            $table->after(column: 'rolled_back_at', callback: function (Blueprint $table): void {
                $table->timestamp(column: 'expires_at')->nullable();
                $table->string(column: 'expiration_action')->nullable();
                $table->timestamp(column: 'actioned_at')->nullable();
                $table->foreignId(column: 'actioned_by')
                    ->nullable()
                    ->constrained(table: config(key: 'approval.approval.users_table', default: 'users'));
            });
        });
    }
};
