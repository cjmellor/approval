<?php

declare(strict_types=1);

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Models\Approval;
use Cjmellor\Approval\Tests\Models\FakeModel;

test(description: 'an approval can use a custom state', closure: function (): void {
    config()->set('approval.states', [
        'pending' => [
            'name' => 'Pending',
            'default' => true,
        ],
        'approved' => [
            'name' => 'Approved',
        ],
        'rejected' => [
            'name' => 'Rejected',
        ],
        'in_review' => [
            'name' => 'In Review',
        ],
    ]);

    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();
    $approval->setState('in_review');

    expect($approval->fresh()->getState())->toBe('in_review');
});

test(description: 'throws exception when setting an undefined state', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();

    expect(value: fn () => $approval->setState(state: 'undefined_state'))
        ->toThrow(
            exception: InvalidArgumentException::class,
            exceptionMessage: "State 'undefined_state' is not defined in the approval configuration."
        );
});

test(description: 'can query approvals by custom state', closure: function (): void {
    config()->set('approval.states', [
        'pending' => ['name' => 'Pending', 'default' => true],
        'approved' => ['name' => 'Approved'],
        'rejected' => ['name' => 'Rejected'],
        'in_review' => ['name' => 'In Review'],
        'needs_info' => ['name' => 'Needs More Information'],
    ]);

    FakeModel::create(['name' => 'Model 1', 'meta' => 'red']);
    $approval1 = Approval::first();
    $approval1->setState('in_review');

    FakeModel::create(['name' => 'Model 2', 'meta' => 'blue']);
    $approval2 = Approval::orderByDesc('id')->first();
    $approval2->setState('needs_info');

    FakeModel::create(['name' => 'Model 3', 'meta' => 'green']);
    $inReviewApprovals = Approval::whereState('in_review')->get();
    $needsInfoApprovals = Approval::whereState('needs_info')->get();
    $pendingApprovals = Approval::whereState('pending')->get();

    expect($inReviewApprovals)->toHaveCount(1)
        ->and($inReviewApprovals->first()->id)->toBe($approval1->id)
        ->and($needsInfoApprovals)->toHaveCount(1)
        ->and($needsInfoApprovals->first()->id)->toBe($approval2->id)
        ->and($pendingApprovals)->toHaveCount(1);
});

test(description: 'setState with a standard state clears custom_state', closure: function (): void {
    config()->set('approval.states', [
        'pending' => ['name' => 'Pending', 'default' => true],
        'approved' => ['name' => 'Approved'],
        'rejected' => ['name' => 'Rejected'],
        'in_review' => ['name' => 'In Review'],
    ]);

    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();
    $approval->setState('in_review');

    expect($approval->fresh()->getState())->toBe('in_review');

    $approval->setState('approved');

    expect($approval->fresh())
        ->state->toBe(ApprovalStatus::Approved)
        ->and($approval->fresh()->getState())->toBe('approved');
});
