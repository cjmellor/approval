<?php

namespace Approval\Approval\Tests;

use Approval\Approval\ApprovalServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ApprovalServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        app('db')->connection()->getSchemaBuilder()->create('comments', function ($table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('comment');
            $table->timestamps();
        });

        $migrationFiles = [
            '2022_02_12_195950_create_approvals_table.php',
            '2023_10_09_204810_add_rolled_back_at_column_to_approvals_table.php',
            '2023_11_17_002135_add_audited_by_column_to_approvals_table.php',
            '2024_03_16_173148_add_foreign_id_column_to_approvals_table.php',
        ];

        foreach ($migrationFiles as $migrationFile) {
            $migration = include __DIR__."/../database/migrations/$migrationFile";

            $migration->up();
        }
    }
}
