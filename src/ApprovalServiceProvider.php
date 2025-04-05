<?php

namespace Cjmellor\Approval;

use Cjmellor\Approval\Console\Commands\ProcessExpiredApprovalsCommand;
use Cjmellor\Approval\Console\Commands\UpgradeToV2Command;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ApprovalServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name(name: 'approval')
            ->hasConfigFile()
            ->hasCommands([
                ProcessExpiredApprovalsCommand::class,
                UpgradeToV2Command::class,
            ])
            ->hasMigrations([
                '2022_02_12_195950_create_approvals_table',
                '2023_10_09_204810_add_rolled_back_at_column_to_approvals_table',
                '2023_11_17_002135_add_audited_by_column_to_approvals_table',
                '2024_03_16_173148_add_foreign_id_column_to_approvals_table',
                '2025_03_04_000001_add_creator_to_approvals_table',
                '2025_03_14_062513_add_expiration_columns_to_approvals_table',
                '2025_03_15_123355_add_custom_state_to_approvals_table',
            ]);
    }

    public function boot(): void
    {
        parent::boot();

        // Skip schema check during console commands
        if (! $this->app->runningInConsole() && class_exists('\\Cjmellor\\Approval\\Models\\Approval')) {
            $this->checkSchemaCompatibility();
        }
    }

    protected function checkSchemaCompatibility(): void
    {
        try {
            if (Schema::hasTable('approvals') && ! Schema::hasColumn('approvals', 'custom_state')) {
                // Using v2 package with v1 schema
                throw new \RuntimeException(
                    "You've upgraded to Approval v2 but need to upgrade your database schema. ".
                    "Run 'php artisan approval:upgrade-to-v2' to safely upgrade."
                );
            }
        } catch (\PDOException $e) {
            // Database connection issues - skip the check
        }
    }
}
