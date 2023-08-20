<?php

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Events\ModelApproved;
use Cjmellor\Approval\Events\ModelRejected;
use Cjmellor\Approval\Events\ModelSetPending;
use Cjmellor\Approval\Models\Approval;
use Cjmellor\Approval\Tests\Models\FakeModel;
use Illuminate\Support\Facades\Event;

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

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Approved);

    $this->assertDatabaseHas(table: 'fake_models', data: $this->fakeModelData);
});

test(description: 'A Model can be Rejected', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();
    $approval->reject();

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Rejected);

    $this->assertDatabaseMissing(table: 'fake_models', data: $this->fakeModelData);
});

test(description: 'A Model can be Postponed', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();
    $approval->postpone();

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Pending);

    $this->assertDatabaseMissing(table: 'fake_models', data: $this->fakeModelData);
});

it(description: 'only changes the status of the requested model', closure: function () {
    FakeModel::create($this->fakeModelData);
    FakeModel::create(['name' => 'Bob', 'meta' => 'green']);

    $modelOneApproval = Approval::first();
    $modelOneApproval->approve();

    expect($modelOneApproval)->fresh()->state->toBe(expected: ApprovalStatus::Approved)
        ->and(Approval::find(id: 2))->state->toBe(expected: ApprovalStatus::Pending);
});

test(description: 'An event is fired when a Model\'s state is changed', closure: function (string $state): void {
    FakeModel::create($this->fakeModelData);

    Event::fake();

    $approval = Approval::first();
    $approval->$state();

    match ($state) {
        'approve' => Event::assertDispatched(ModelApproved::class),
        'reject' => Event::assertDispatched(ModelRejected::class),
        'postpone' => Event::assertDispatched(ModelSetPending::class),
    };
})->with(['approve', 'reject', 'postpone']);
