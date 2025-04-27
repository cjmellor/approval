<?php

declare(strict_types=1);

namespace Cjmellor\Approval;

use Cjmellor\Approval\Console\Commands\ProcessExpiredApprovalsCommand;
use Cjmellor\Approval\Console\Commands\UpgradeToV2Command;
use Cjmellor\Approval\Models\Approval;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Override;
use PDOException;
use RuntimeException;

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
        if (! $this->app->runningInConsole() && class_exists(Approval::class)) {
            $this->checkSchemaCompatibility();
        }
    }

    /**
     * Register any package services.
     */
    #[Override]
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
            throw_if(Schema::hasTable('approvals') && ! Schema::hasColumn('approvals', 'custom_state'), new RuntimeException(
                "You've upgraded to Approval v2 but need to upgrade your database schema. ".
                "Run 'php artisan approval:upgrade-to-v2' to safely upgrade."
            ));
        } catch (PDOException) {
            // Database connection issues - skip the check
        }
    }
}
