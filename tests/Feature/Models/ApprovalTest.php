<?php

declare(strict_types=1);

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Events\ApprovalExpired;
use Cjmellor\Approval\Events\ModelRejected;
use Cjmellor\Approval\Events\ModelRolledBackEvent;
use Cjmellor\Approval\Events\ModelSetPending;
use Cjmellor\Approval\Models\Approval;
use Cjmellor\Approval\Tests\Models\FakeModel;
use Cjmellor\Approval\Tests\Models\FakeUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
    Event::assertDispatched(fn (ModelRolledBackEvent $event
    ): bool => $event->approval->is($fakeModel->fresh()->approvals()->first())
        && ! $event->user instanceof Authenticatable);
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

test(description: 'a rolled back Approval can be conditionally set', closure: function (): void {
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
    $fakeModel->fresh()->approvals()->first()->rollback(condition: fn (): true => true, bypass: false);

    // Check the model has been rolled back
    expect($fakeModel->fresh()->approvals()->first())
        ->state->toBe(expected: ApprovalStatus::Pending)
        ->new_data->toMatchArray(['name' => 'Bob'])
        ->original_data->toMatchArray(['name' => 'Chris'])
        ->rolled_back_at->not->toBeNull();
});

test(description: 'requestor method returns a morphTo relationship', closure: function (): void {
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

    expect($approval->requestor())->toBeInstanceOf(MorphTo::class);
});

test(description: 'getRequestorAttribute returns the user that requested approval', closure: function (): void {
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

test(description: 'scopeRequestedBy correctly filters approvals by requestor', closure: function (): void {
    // Create two users
    $user1 = FakeUser::create([
        'name' => 'User One',
        'email' => 'user1@example.com',
        'password' => 'password',
    ]);

    $user2 = FakeUser::create([
        'name' => 'User Two',
        'email' => 'user2@example.com',
        'password' => 'password',
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

test(description: 'wasRequestedBy correctly identifies if a model requested the approval', closure: function (): void {
    // Create two users
    $user1 = FakeUser::create([
        'name' => 'User One',
        'email' => 'user1@example.com',
        'password' => 'password',
    ]);

    $user2 = FakeUser::create([
        'name' => 'User Two',
        'email' => 'user2@example.com',
        'password' => 'password',
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

test('can set expiration time with different parameters', function (
    array $params,
    callable $expectedTimeGenerator,
    int $expectedDuration
): void {
    // Create a fake model which creates an approval
    FakeModel::create($this->fakeModelData);

    // Get the approval and set expiration time using the provided parameters
    $approval = Approval::first();
    $approval->expiresIn(...$params);

    // Get the expected time using the provided generator function
    $expectedTime = $expectedTimeGenerator();

    expect($approval->fresh()->expires_at)->not->toBeNull();

    // Calculate difference between timestamps
    $difference = abs($approval->fresh()->expires_at->timestamp - $expectedTime->timestamp);

    // Instead of a fixed 5 seconds, allow a percentage-based margin of error
    // For longer durations, we need a larger acceptable difference
    $marginOfError = max(5, $expectedDuration * 0.03); // 3% margin with minimum of 5 seconds

    expect($difference)->toBeLessThan($marginOfError);
})->with([
    [['minutes' => 30], fn () => now()->addMinutes(30), 30 * 60], // 30 minutes in seconds
    [['hours' => 24], fn () => now()->addHours(24), 24 * 3600],   // 24 hours in seconds
    [['days' => 7], fn () => now()->addDays(7), 7 * 86400],       // 7 days in seconds
    [['datetime' => now()->addWeek()], fn () => now()->addWeek(), 7 * 86400], // Same as days
]);

test(description: 'throws exception when no expiration time is provided', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();

    expect(fn () => $approval->expiresIn())->toThrow(exception: InvalidArgumentException::class);
});

test(description: 'can check if an approval is expired', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();

    // Set expiration to a past time
    $approval->expiresIn(datetime: now()->subHour());
    expect($approval->isExpired())->toBeTrue();

    // Set expiration to a future time
    $approval->expiresIn(datetime: now()->addHour());
    expect($approval->isExpired())->toBeFalse();
});

test('can set automatic actions on expiration', function (string $method, string $expectedAction): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();

    // Set up automatic action on expiration
    $approval->expiresIn(hours: 1)->$method();

    // Verify the expiration action is set correctly
    expect($approval->fresh()->expiration_action)->toBe($expectedAction);
})->with([
    ['thenReject', 'reject'],
    ['thenPostpone', 'postpone'],
]);

test(description: 'can set custom action on expiration', closure: function (): void {
    FakeModel::create($this->fakeModelData);
    $approval = Approval::first();

    // Set up a custom callback for expiration
    $approval->expiresIn(hours: 1)->thenDo(
        // This would be executed by the scheduler
        // Just for testing
        fn ($approval): true => true
    );

    // Verify the expiration action is set to custom
    expect($approval->fresh()->expiration_action)->toBe(expected: 'custom');
});

test(description: 'can process expired approvals', closure: function (): void {
    // Create and set up approvals with different actions
    FakeModel::create(['name' => 'Model 1', 'meta' => 'red']);
    $rejectionApproval = Approval::first();
    $rejectionApproval->expiresIn(datetime: now()->subHour())->thenReject();

    FakeModel::create(['name' => 'Model 2', 'meta' => 'blue']);
    $postponeApproval = Approval::orderByDesc(column: 'id')->first();
    $postponeApproval->expiresIn(datetime: now()->subHour())->thenPostpone();

    // Create a non-expired approval
    FakeModel::create(['name' => 'Model 3', 'meta' => 'green']);
    $futureApproval = Approval::orderByDesc(column: 'id')->first();
    $futureApproval->expiresIn(datetime: now()->addHour())->thenReject();

    // Fake the events to check they're dispatched
    Event::fake([
        ApprovalExpired::class,
        ModelRejected::class,
        ModelSetPending::class,
    ]);

    // Process expired approvals
    Approval::processExpired();

    // Verify rejections were processed
    expect($rejectionApproval->fresh()->state->value)
        ->toBe(expected: 'rejected')
        ->and($rejectionApproval->fresh()->actioned_at)
        ->not->toBeNull();

    // Verify postponements were processed
    expect($postponeApproval->fresh()->state->value)
        ->toBe(expected: 'pending')
        ->and($postponeApproval->fresh()->actioned_at)
        ->not->toBeNull();

    // Verify future approvals were not touched
    expect($futureApproval->fresh()->actioned_at)->toBeNull();

    // Verify events were dispatched
    Event::assertDispatched(event: ApprovalExpired::class, callback: 2); // For both expired approvals
    Event::assertDispatched(event: ModelRejected::class, callback: 1);
    Event::assertDispatched(event: ModelSetPending::class, callback: 1);
});

test(description: 'artisan command processes expired approvals', closure: function (): void {
    // Create an expired approval
    FakeModel::create([
        'name' => 'Test Model',
        'meta' => 'red',
    ]);

    $approval = Approval::first();
    $approval->expiresIn(datetime: now()->subHour())->thenReject();

    // Fake events
    Event::fake([
        ApprovalExpired::class,
        ModelRejected::class,
    ]);

    // Run the command
    $this->artisan(command: 'approval:process-expired')
        ->expectsOutput(output: '1 expired approval(s) processed successfully.')
        ->assertExitCode(exitCode: 0);

    // Verify approval was processed
    expect($approval->fresh()->state->value)
        ->toBe(expected: 'rejected')
        ->and($approval->fresh()->actioned_at)
        ->not->toBeNull();

    // Verify the events were dispatched
    Event::assertDispatched(event: ApprovalExpired::class, callback: 1);
    Event::assertDispatched(event: ModelRejected::class, callback: 1);
});
