<?php

declare(strict_types=1);

use Cjmellor\Approval\Models\Approval;
use Cjmellor\Approval\Tests\Models\FakeModel;

test(description: 'an approval can use a custom state', closure: function (): void {
    // Setup config with a custom state
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

    // Create a model that will generate an approval
    FakeModel::create($this->fakeModelData);

    // Get the approval and set the custom state
    $approval = Approval::first();
    $approval->setState('in_review');

    // Assert the state was set correctly
    expect($approval->fresh()->getState())->toBe('in_review');
});

test(description: 'throws exception when setting an undefined state', closure: function (): void {
    // Create a model that will generate an approval
    FakeModel::create($this->fakeModelData);

    // Get the approval
    $approval = Approval::first();

    // Assert that setting an undefined state throws an exception
    expect(value: fn () => $approval->setState(state: 'undefined_state'))
        ->toThrow(
            exception: InvalidArgumentException::class,
            exceptionMessage: "State 'undefined_state' is not defined in the approval configuration."
        );
});

test(description: 'can query approvals by custom state', closure: function (): void {
    // Setup config with custom states
    config()->set('approval.states', [
        'pending' => ['name' => 'Pending', 'default' => true],
        'approved' => ['name' => 'Approved'],
        'rejected' => ['name' => 'Rejected'],
        'in_review' => ['name' => 'In Review'],
        'needs_info' => ['name' => 'Needs More Information'],
    ]);

    // Create approvals with different states
    FakeModel::create(['name' => 'Model 1', 'meta' => 'red']);
    $approval1 = Approval::first();
    $approval1->setState('in_review');

    FakeModel::create(['name' => 'Model 2', 'meta' => 'blue']);
    $approval2 = Approval::orderByDesc('id')->first();
    $approval2->setState('needs_info');

    FakeModel::create(['name' => 'Model 3', 'meta' => 'green']);
    // This one stays as pending (default)

    // Query approvals by custom state
    $inReviewApprovals = Approval::whereState('in_review')->get();
    $needsInfoApprovals = Approval::whereState('needs_info')->get();
    $pendingApprovals = Approval::whereState('pending')->get();

    // Assert queries return the correct results
    expect($inReviewApprovals)->toHaveCount(1)
        ->and($inReviewApprovals->first()->id)->toBe($approval1->id)
        ->and($needsInfoApprovals)->toHaveCount(1)
        ->and($needsInfoApprovals->first()->id)->toBe($approval2->id)
        ->and($pendingApprovals)->toHaveCount(1);
});
