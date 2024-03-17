<?php

use Approval\Approval\Enums\ApprovalStatus;
use Approval\Approval\Events\ModelRolledBackEvent;
use Approval\Approval\Tests\Models\Comment;
use Illuminate\Support\Facades\Event;

test(description: 'an Approved Model can be rolled back and doesn\'t bypass', closure: function (): void {
    // Build a query
    $fakeModel = new Comment();

    $fakeModel->comment = 'I like pie';

    // Save the model, bypassing approval
    $fakeModel->withoutApproval()->save();

    // Update a fresh instance of the model
    $fakeModel->fresh()->update(['comment' => 'The weather is nice']);

    // Approve the new changes
    $fakeModel->fresh()->approvals()->first()->approve();

    // Test for Events
    Event::fake();

    // Rollback the data
    $fakeModel->fresh()->approvals()->first()->rollback(bypass: false);

    // Check the model has been rolled back
    expect($fakeModel->fresh()->approvals()->first())
        ->state->toBe(expected: ApprovalStatus::Pending)
        ->new_data->toMatchArray(['comment' => 'I like pie'])
        ->original_data->toMatchArray(['comment' => 'The weather is nice'])
        ->rolled_back_at->not->toBeNull();

    // Assert the Events were fired
    Event::assertDispatched(fn (ModelRolledBackEvent $event): bool => $event->approval->is($fakeModel->fresh()->approvals()->first())
        && $event->user === null);
});

test(description: 'an Approved Model can be rolled back and bypass', closure: function (): void {
    // Build a query
    $fakeModel = new Comment();

    $fakeModel->comment = 'I like pie';

    // Save the model, bypassing approval
    $fakeModel->withoutApproval()->save();

    // Update a fresh instance of the model
    $fakeModel->fresh()->update(['comment' => 'The weather is nice']);

    // Approve the new changes
    $fakeModel->fresh()->approvals()->first()->approve();

    // Rollback the data
    $fakeModel->fresh()->approvals()->first()->rollback();

    // Check the model has been rolled back
    expect($fakeModel->fresh()->approvals()->first())
        ->state->toBe(expected: ApprovalStatus::Approved)
        ->new_data->toMatchArray(['comment' => 'I like pie'])
        ->original_data->toMatchArray(['comment' => 'The weather is nice'])
        ->rolled_back_at->not->toBeNull();
});

test(description: 'a rolled back Approval can be conditionally set', closure: function () {
    // Build a query
    $fakeModel = new Comment();

    $fakeModel->comment = 'I like pie';

    // Save the model, bypassing approval
    $fakeModel->withoutApproval()->save();

    // Update a fresh instance of the model
    $fakeModel->fresh()->update(['comment' => 'The weather is nice']);

    // Approve the new changes
    $fakeModel->fresh()->approvals()->first()->approve();

    // Conditionally rollback the data
    $fakeModel->fresh()->approvals()->first()->rollback(condition: fn () => true, bypass: false);

    // Check the model has been rolled back
    expect($fakeModel->fresh()->approvals()->first())
        ->state->toBe(expected: ApprovalStatus::Pending)
        ->new_data->toMatchArray(['comment' => 'I like pie'])
        ->original_data->toMatchArray(['comment' => 'The weather is nice'])
        ->rolled_back_at->not->toBeNull();
});
