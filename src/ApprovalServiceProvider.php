<?php

declare(strict_types=1);

namespace Cjmellor\Approval;

use Cjmellor\Approval\Console\Commands\ProcessExpiredApprovalsCommand;
use Cjmellor\Approval\Console\Commands\UpgradeToV2Command;
use Illuminate\Support\ServiceProvider;
use Override;

class ApprovalServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes(
            paths: [__DIR__.'/../config/approval.php' => config_path(path: 'approval.php')],
            groups: 'approval-config'
        );

        $this->publishes(
            paths: [__DIR__.'/../database/migrations/' => database_path(path: 'migrations')],
            groups: 'approval-migrations'
        );

        $this->loadMigrationsFrom(paths: __DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessExpiredApprovalsCommand::class,
                UpgradeToV2Command::class,
            ]);
        }
    }

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/approval.php',
            key: 'approval'
        );
    }
}
