<?php

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Events\ModelRolledBackEvent;
use Cjmellor\Approval\Tests\Models\FakeModel;
use Illuminate\Support\Facades\Event;

test(description: 'an Approved Model can be rolled back', closure: function (): void {
        // build a query
    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->meta = 'green';

    // save the model, bypassing approval
    $fakeModel->withoutApproval()->save();

    // Update a fresh instance of the model
    $fakeModel->fresh()->update(['name' => 'Chris']);

    // Approve the new changes
    $fakeModel->fresh()->approvals()->first()->approve();

    // Test for Events
    Event::fake();

    // Rollback the data
    $fakeModel->fresh()->approvals()->first()->rollback();

    // Check the model has been rolled back
    expect($fakeModel->fresh()->approvals()->first())
        ->state->toBe(expected: ApprovalStatus::Pending)
        ->new_data->toMatchArray(['name' => 'Bob'])
        ->original_data->toMatchArray(['name' => 'Chris'])
        ->rolled_back_at->not->toBeNull();

    // Asser the Events were fired
    Event::assertDispatched(ModelRolledBackEvent::class);
});

