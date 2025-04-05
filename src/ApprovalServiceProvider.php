<?php

namespace Cjmellor\Approval;

use Cjmellor\Approval\Console\Commands\ProcessExpiredApprovalsCommand;
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
            ]);
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
}
