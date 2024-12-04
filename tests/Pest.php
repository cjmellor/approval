<?php

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Tests\Models\FakeModel;
use Cjmellor\Approval\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(hook: function (): void {
        $this->approvalData = [
            'approvalable_type' => FakeModel::class,
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
