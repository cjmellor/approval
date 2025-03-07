<?php

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Events\ModelRolledBackEvent;
use Cjmellor\Approval\Models\Approval;
use Cjmellor\Approval\Tests\Models\FakeModel;
use Cjmellor\Approval\Tests\Models\FakeUser;
use Illuminate\Support\Facades\Event;

test(description: 'an Approved Model can be rolled back and doesn\'t bypass', closure: function (): void {
    // Build a query
    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->meta = 'green';

    // Save the model, bypassing approval
    $fakeModel->withoutApproval()->save();

    // Update a fresh instance of the model
    $fakeModel->fresh()->update(['name' => 'Chris']);

    // Approve the new changes
    $fakeModel->fresh()->approvals()->first()->approve();

    // Test for Events
    Event::fake();

    // Rollback the data
    $fakeModel->fresh()->approvals()->first()->rollback(bypass: false);

    // Check the model has been rolled back
    expect($fakeModel->fresh()->approvals()->first())
        ->state->toBe(expected: ApprovalStatus::Pending)
        ->new_data->toMatchArray(['name' => 'Bob'])
        ->original_data->toMatchArray(['name' => 'Chris'])
        ->rolled_back_at->not->toBeNull();

    // Assert the Events were fired
    Event::assertDispatched(fn (ModelRolledBackEvent $event): bool => $event->approval->is($fakeModel->fresh()->approvals()->first())
        && $event->user === null);
});

test(description: 'an Approved Model can be rolled back and bypass', closure: function (): void {
    // Build a query
    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->meta = 'green';

    // Save the model, bypassing approval
    $fakeModel->withoutApproval()->save();

    // Update a fresh instance of the model
    $fakeModel->fresh()->update(['name' => 'Chris']);

    // Approve the new changes
    $fakeModel->fresh()->approvals()->first()->approve();

    // Rollback the data
    $fakeModel->fresh()->approvals()->first()->rollback();

    // Check the model has been rolled back
    expect($fakeModel->fresh()->approvals()->first())
        ->state->toBe(expected: ApprovalStatus::Approved)
        ->new_data->toMatchArray(['name' => 'Bob'])
        ->original_data->toMatchArray(['name' => 'Chris'])
        ->rolled_back_at->not->toBeNull();
});

test(description: 'a rolled back Approval can be conditionally set', closure: function () {
    // Build a query
    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->meta = 'green';

    // Save the model, bypassing approval
    $fakeModel->withoutApproval()->save();

    // Update a fresh instance of the model
    $fakeModel->fresh()->update(['name' => 'Chris']);

    // Approve the new changes
    $fakeModel->fresh()->approvals()->first()->approve();

    // Conditionally rollback the data
    $fakeModel->fresh()->approvals()->first()->rollback(condition: fn () => true, bypass: false);

    // Check the model has been rolled back
    expect($fakeModel->fresh()->approvals()->first())
        ->state->toBe(expected: ApprovalStatus::Pending)
        ->new_data->toMatchArray(['name' => 'Bob'])
        ->original_data->toMatchArray(['name' => 'Chris'])
        ->rolled_back_at->not->toBeNull();
});

test(description: 'requestor method returns a morphTo relationship', closure: function () {
    // Create a user
    $user = FakeUser::create($this->fakeUserData);
    
    // Set the user as authenticated
    $this->be($user);
    
    // Create model with approval
    $fakeModel = new FakeModel();
    $fakeModel->name = 'Test Model';
    $fakeModel->save();
    
    // Get the approval directly from the database
    $approval = Approval::first();
    
    expect($approval->requestor())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test(description: 'getRequestorAttribute returns the user that requested approval', closure: function () {
    // Create a user
    $user = FakeUser::create($this->fakeUserData);
    
    // Set the user as authenticated
    $this->be($user);
    
    // Create model with approval
    $fakeModel = new FakeModel();
    $fakeModel->name = 'Test Model';
    $fakeModel->save();
    
    // Get the approval directly from the database
    $approval = Approval::first();
    
    expect($approval->requestor)
        ->toBeInstanceOf(FakeUser::class)
        ->id->toBe($user->id)
        ->name->toBe($user->name);
});

test(description: 'scopeRequestedBy correctly filters approvals by requestor', closure: function () {
    // Create two users
    $user1 = FakeUser::create([
        'name' => 'User One',
        'email' => 'user1@example.com',
        'password' => 'password'
    ]);
    
    $user2 = FakeUser::create([
        'name' => 'User Two',
        'email' => 'user2@example.com',
        'password' => 'password'
    ]);
    
    // Create approvals as user1
    $this->be($user1);
    $model1 = new FakeModel();
    $model1->name = 'Model by User 1';
    $model1->save();
    
    // Create approvals as user2
    $this->be($user2);
    $model2 = new FakeModel();
    $model2->name = 'Model by User 2';
    $model2->save();
    
    // Query approvals by user1
    $user1Approvals = Approval::requestedBy($user1)->get();
    
    // Assert that only approvals by user1 are returned
    expect($user1Approvals)->toHaveCount(1);
    expect($user1Approvals->first()->creator_id)->toBe($user1->id);
});

test(description: 'wasRequestedBy correctly identifies if a model requested the approval', closure: function () {
    // Create two users
    $user1 = FakeUser::create([
        'name' => 'User One',
        'email' => 'user1@example.com',
        'password' => 'password'
    ]);
    
    $user2 = FakeUser::create([
        'name' => 'User Two',
        'email' => 'user2@example.com',
        'password' => 'password'
    ]);
    
    // Create approval as user1
    $this->be($user1);
    $model = new FakeModel();
    $model->name = 'Test Model';
    $model->save();
    
    $approval = Approval::first();
    
    expect($approval->wasRequestedBy($user1))->toBeTrue();
    expect($approval->wasRequestedBy($user2))->toBeFalse();
});
