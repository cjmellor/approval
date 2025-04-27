<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class UpgradeToV2Command extends Command
{
    protected $signature = 'approval:upgrade-to-v2';

    protected $description = 'Safely upgrade the approval package database schema from v1 to v2';

    public function handle(): int
    {
        // Check if already on v2 schema
        if (Schema::hasColumn('approvals', 'custom_state')) {
            $this->info('✅ Your database is already using the v2 schema.');

            return self::SUCCESS;
        }

        $this->info('Starting upgrade from Approval v1 to v2...');

        if (! $this->confirm('Have you backed up your database? This operation modifies schema.', false)) {
            $this->warn('Please backup your database before proceeding.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () {
                // 1. Log current approvals count for verification
                $initialCount = DB::table('approvals')->count();
                $this->info("Found {$initialCount} existing approval records to migrate");

                // 2. Add custom_state column
                Schema::table('approvals', function (Blueprint $table) {
                    $table->string('custom_state')->nullable()->after('state');
                });

                // 3. Verify data integrity
                $finalCount = DB::table('approvals')->count();
                if ($finalCount !== $initialCount) {
                    throw new RuntimeException("Data verification failed: before ({$initialCount}) vs after ({$finalCount})");
                }

                $this->info('✅ Database schema successfully upgraded to v2');
            });

            $this->info('✅ Upgrade completed successfully!');
            $this->info('To use custom approval states, run: php artisan vendor:publish --tag="approval-config"');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Upgrade failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
