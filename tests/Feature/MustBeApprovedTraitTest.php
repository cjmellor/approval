<?php

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Models\Approval;

beforeEach(function () {
    $this->approvalData = [
        'approvalable_type' => 'App\Models\User',
        'approvalable_id' => 1,
        'state' => ApprovalStatus::Pending,
        'new_data' => json_encode(['name' => 'Chris']),
        'original_data' => json_encode(['name' => 'Bob']),
    ];
});

it(description: 'stores the data correctly in the database')
    ->tap(
        fn () => Approval::create($this->approvalData)
    )->assertDatabaseHas('approvals', [
        'approvalable_type' => 'App\Models\User',
        'approvalable_id' => 1,
        'state' => ApprovalStatus::Pending,
    ]);
