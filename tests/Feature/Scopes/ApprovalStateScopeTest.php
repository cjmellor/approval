<?php

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Models\Approval;
use Cjmellor\Approval\Tests\Models\FakeModel;

beforeEach(closure: function (): void {
    $this->fakeModelData = [
        'name' => 'Chris',
        'meta' => 'red',
    ];
});

test('Check if an Approval Model is approved', closure: function (): void {
    $this->approvalData = [
        'approvalable_type' => 'App\Models\FakeModel',
        'approvalable_id' => 1,
        'state' => ApprovalStatus::Approved,
        'new_data' => json_encode(['name' => 'Chris']),
        'original_data' => json_encode(['name' => 'Bob']),
    ];

    $approval = Approval::create($this->approvalData);

    expect($approval)->state->toBe(ApprovalStatus::Approved);
});

// same but for pending and rejected
test('Check if an Approval Model is pending', closure: function (): void {
    $this->approvalData = [
        'approvalable_type' => 'App\Models\FakeModel',
        'approvalable_id' => 1,
        'state' => ApprovalStatus::Pending,
        'new_data' => json_encode(['name' => 'Chris']),
        'original_data' => json_encode(['name' => 'Bob']),
    ];

    $approval = Approval::create($this->approvalData);

    expect($approval)->state->toBe(ApprovalStatus::Pending);
});

test('Check if an Approval Model is rejected', closure: function (): void {
    $this->approvalData = [
        'approvalable_type' => 'App\Models\FakeModel',
        'approvalable_id' => 1,
        'state' => ApprovalStatus::Rejected,
        'new_data' => json_encode(['name' => 'Chris']),
        'original_data' => json_encode(['name' => 'Bob']),
    ];

    $approval = Approval::create($this->approvalData);

    expect($approval)->state->toBe(ApprovalStatus::Rejected);
});

test(description: 'A Model can be Approved', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();
    $approval->approve();

    $this->assertDatabaseHas(table: 'fake_models', data: [
        'name' => 'Chris',
        'meta' => 'red',
    ]);
});
