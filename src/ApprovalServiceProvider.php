<?php

namespace Cjmellor\Approval;

use Cjmellor\Approval\Console\Commands\ProcessExpiredApprovalsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ApprovalServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name(name: 'approval')
            ->hasConfigFile()
            ->hasCommand(commandClassName: ProcessExpiredApprovalsCommand::class)
            ->hasMigrations([
                '2022_02_12_195950_create_approvals_table',
                '2023_10_09_204810_add_rolled_back_at_column_to_approvals_table',
                '2023_11_17_002135_add_audited_by_column_to_approvals_table',
                '2024_03_16_173148_add_foreign_id_column_to_approvals_table',
                '2025_03_04_000001_add_creator_to_approvals_table',
                '2025_03_14_062513_add_expiration_columns_to_approvals_table',
            ]);
    }
}
