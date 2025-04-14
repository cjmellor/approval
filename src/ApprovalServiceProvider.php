<?php

namespace Cjmellor\Approval;

use Cjmellor\Approval\Console\Commands\ProcessExpiredApprovalsCommand;
use Cjmellor\Approval\Console\Commands\UpgradeToV2Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class ApprovalServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        // Register config file
        $this->publishes(
            paths: [__DIR__.'/../config/approval.php' => config_path(path: 'approval.php')],
            groups: 'approval-config'
        );

        // Register migrations
        $this->publishes(
            paths: [__DIR__.'/../database/migrations/' => database_path(path: 'migrations')],
            groups: 'approval-migrations'
        );

        // Load migrations
        $this->loadMigrationsFrom(paths: __DIR__.'/../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessExpiredApprovalsCommand::class,
                UpgradeToV2Command::class,
            ]);
        }

        // Skip schema check during console commands
        if (! $this->app->runningInConsole() && class_exists('\\Cjmellor\\Approval\\Models\\Approval')) {
            $this->checkSchemaCompatibility();
        }
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/approval.php',
            key: 'approval'
        );
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
