<?php

use Approval\Approval\Enums\ApprovalStatus;
use Approval\Approval\Events\ModelApproved;
use Approval\Approval\Events\ModelRejected;
use Approval\Approval\Events\ModelSetPending;
use Approval\Approval\Models\Approval;
use Approval\Approval\Tests\Models\Comment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

test('Check if an Approval Model is state', closure: function (ApprovalStatus $state): void {
    $this->approvalData = [
        'approvalable_type' => 'App\Models\Comment',
        'approvalable_id' => 1,
        'state' => $state,
        'new_data' => json_encode(['comment' => 'Hello']),
        'original_data' => json_encode(['comment' => 'Goodbye']),
    ];

    $approval = Approval::create($this->approvalData);

    expect($approval)->state->toBe($state);
})->with([
    ApprovalStatus::Approved,
    ApprovalStatus::Pending,
    ApprovalStatus::Rejected,
]);

test(description: 'A Model can be Approved', closure: function (): void {
    Comment::create($this->fakeModelData);

    $approval = Approval::first();
    $approval->approve();

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Approved);

    $this->assertDatabaseHas(table: 'comments', data: $this->fakeModelData);
});

test(description: 'A Model can be Rejected', closure: function (): void {
    Comment::create($this->fakeModelData);

    $approval = Approval::first();
    $approval->reject();

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Rejected);

    $this->assertDatabaseMissing(table: 'comments', data: $this->fakeModelData);
});

test(description: 'A Model can be Postponed', closure: function (): void {
    Comment::create($this->fakeModelData);

    $approval = Approval::first();
    $approval->postpone();

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Pending);

    $this->assertDatabaseMissing(table: 'comments', data: $this->fakeModelData);
});

it(description: 'only changes the status of the requested model', closure: function () {
    Comment::create($this->fakeModelData);
    Comment::create(['name' => 'Bob', 'meta' => 'green']);

    $modelOneApproval = Approval::first();
    $modelOneApproval->approve();

    expect($modelOneApproval)->fresh()->state->toBe(expected: ApprovalStatus::Approved)
        ->and(Approval::find(id: 2))->state->toBe(expected: ApprovalStatus::Pending);
});

test(description: 'An event is fired when a Model\'s state is changed', closure: function (string $state): void {
    Comment::create($this->fakeModelData);

    Event::fake();

    $approval = Approval::first();
    $approval->$state();

    match ($state) {
        'approve' => Event::assertDispatched(ModelApproved::class),
        'reject' => Event::assertDispatched(ModelRejected::class),
        'postpone' => Event::assertDispatched(ModelSetPending::class),
    };
})->with(['approve', 'reject', 'postpone']);

test(description: 'A Model can be Approved if a condition is met', closure: function (): void {
    Comment::create($this->fakeModelData);

    Event::fake();

    $approval = Approval::first();
    $approval->approveIf(boolean: true);

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Approved);

    Event::assertDispatched(event: ModelApproved::class);

    $this->assertDatabaseHas(table: 'comments', data: $this->fakeModelData);
});

test(description: 'A Model can be Approved unless a condition is met', closure: function (): void {
    Comment::create($this->fakeModelData);

    Event::fake();

    $approval = Approval::first();
    $approval->approveUnless(boolean: false);

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Approved);

    Event::assertDispatched(event: ModelApproved::class);

    $this->assertDatabaseHas(table: 'comments', data: $this->fakeModelData);
});

test(description: 'A Model can be Rejected if a condition is met', closure: function (): void {
    Comment::create($this->fakeModelData);

    Event::fake();

    $approval = Approval::first();
    $approval->rejectIf(boolean: true);

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Rejected);

    Event::assertDispatched(event: ModelRejected::class);

    $this->assertDatabaseMissing(table: 'comments', data: $this->fakeModelData);
});

test(description: 'A Model can be Rejected unless a condition is met', closure: function (): void {
    Comment::create($this->fakeModelData);

    Event::fake();

    $approval = Approval::first();
    $approval->rejectUnless(boolean: false);

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Rejected);

    Event::assertDispatched(event: ModelRejected::class);

    $this->assertDatabaseMissing(table: 'comments', data: $this->fakeModelData);
});

test(description: 'A Model can be Postponed if a condition is met', closure: function (): void {
    Comment::create($this->fakeModelData);

    Event::fake();

    $approval = Approval::first();
    $approval->postponeIf(boolean: true);

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Pending);

    Event::assertDispatched(event: ModelSetPending::class);

    $this->assertDatabaseMissing(table: 'comments', data: $this->fakeModelData);
});

test(description: 'A Model can be Postponed unless a condition is met', closure: function (): void {
    Comment::create($this->fakeModelData);

    Event::fake();

    $approval = Approval::first();
    $approval->postponeUnless(boolean: false);

    expect($approval)->fresh()->state->toBe(ApprovalStatus::Pending);

    Event::assertDispatched(event: ModelSetPending::class);

    $this->assertDatabaseMissing(table: 'comments', data: $this->fakeModelData);
});

test(description: 'The model approver is listed correctly', closure: function () {
    Schema::create('fake_users', callback: function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string(column: 'name');
        $table->string(column: 'email')->unique();
        $table->string('password');
    });

    class FakeUser extends \Illuminate\Foundation\Auth\User
    {
        protected $guarded = [];

        protected $table = 'fake_users';

        public $timestamps = false;
    }

    $user = FakeUser::create([
        'name' => 'Chris Mellor',
        'email' => 'chris@mellor.pizza',
        'password' => 'password',
    ]);

    $this->be($user);

    Comment::create($this->fakeModelData);

    $approval = Approval::first();
    $approval->approve();

    expect($approval)->fresh()->audited_by->toBe(expected: $user->id);

    Schema::dropIfExists('fake_users');
});
