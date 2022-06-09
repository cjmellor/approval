<?php

namespace Cjmellor\Approval;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ApprovalServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name(name: 'approval')
            ->hasConfigFile()
            ->hasMigration(migrationFileName: '2022_02_12_195950_create_approvals_table');
    }
}
