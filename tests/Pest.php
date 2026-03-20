<?php

declare(strict_types=1);

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Tests\TestCase;
use Cjmellor\Approval\Tests\Traits\WithTestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Workbench\App\Models\User;

uses(TestCase::class, RefreshDatabase::class, WithTestUser::class)
    ->beforeEach(hook: function (): void {
        $this->approvalData = [
            'approvalable_type' => User::class,
            'approvalable_id' => 1,
            'state' => ApprovalStatus::Pending,
            'new_data' => json_encode(['name' => 'Chris']),
            'original_data' => json_encode(['name' => 'Bob']),
        ];

        $this->fakeModelData = [
            'name' => 'Chris',
            'meta' => 'red',
        ];

        $this->fakeUserData = [
            'name' => 'Chris Mellor',
            'email' => 'chris@mellor.pizza',
            'password' => 'password',
        ];
    })
    ->in(__DIR__);
