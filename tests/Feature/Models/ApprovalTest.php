<?php

declare(strict_types=1);

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Enums\ExpirationAction;
use Cjmellor\Approval\Events\ApprovalExpired;
use Cjmellor\Approval\Events\ModelRejected;
use Cjmellor\Approval\Events\ModelRolledBack;
use Cjmellor\Approval\Events\ModelSetPending;
use Cjmellor\Approval\Models\Approval;
use Cjmellor\Approval\Tests\Models\FakeModel;
use Cjmellor\Approval\Tests\Models\FakeUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

test(description: 'an Approved Model can be rolled back and doesn\'t bypass', closure: function (): void {
    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->meta = 'green';

    $fakeModel->withoutApproval()->save();

    $fakeModel->fresh()->update(['name' => 'Chris']);

    $fakeModel->fresh()->approvals()->first()->approve();

    Event::fake();

    $fakeModel->fresh()->approvals()->first()->rollback(bypass: false);

    expect($fakeModel->fresh()->approvals()->first())
        ->state->toBe(expected: ApprovalStatus::Pending)
        ->new_data->toMatchArray(['name' => 'Bob'])
        ->original_data->toMatchArray(['name' => 'Chris'])
        ->rolled_back_at->not->toBeNull();

    Event::assertDispatched(fn (ModelRolledBack $event
    ): bool => $event->approval->is($fakeModel->fresh()->approvals()->first())
        && ! $event->user instanceof Authenticatable);
});

test(description: 'an Approved Model can be rolled back and bypass', closure: function (): void {
    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->meta = 'green';

    $fakeModel->withoutApproval()->save();

    $fakeModel->fresh()->update(['name' => 'Chris']);

    $fakeModel->fresh()->approvals()->first()->approve();

    $fakeModel->fresh()->approvals()->first()->rollback();

    expect($fakeModel->fresh()->approvals()->first())
        ->state->toBe(expected: ApprovalStatus::Approved)
        ->new_data->toMatchArray(['name' => 'Bob'])
        ->original_data->toMatchArray(['name' => 'Chris'])
        ->rolled_back_at->not->toBeNull();
});

test(description: 'a rolled back Approval can be conditionally set', closure: function (): void {
    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->meta = 'green';

    $fakeModel->withoutApproval()->save();

    $fakeModel->fresh()->update(['name' => 'Chris']);

    $fakeModel->fresh()->approvals()->first()->approve();

    $fakeModel->fresh()->approvals()->first()->rollback(condition: fn (): true => true, bypass: false);

    expect($fakeModel->fresh()->approvals()->first())
        ->state->toBe(expected: ApprovalStatus::Pending)
        ->new_data->toMatchArray(['name' => 'Bob'])
        ->original_data->toMatchArray(['name' => 'Chris'])
        ->rolled_back_at->not->toBeNull();
});

test(description: 'requestor method returns a morphTo relationship', closure: function (): void {
    $user = FakeUser::create($this->fakeUserData);

    $this->be($user);

    $fakeModel = new FakeModel();

    $fakeModel->name = 'Test Model';
    $fakeModel->save();

    $approval = Approval::first();

    expect($approval->requestor())->toBeInstanceOf(MorphTo::class);
});

test(description: 'getRequestorAttribute returns the user that requested approval', closure: function (): void {
    $user = FakeUser::create($this->fakeUserData);

    $this->be($user);

    $fakeModel = new FakeModel();

    $fakeModel->name = 'Test Model';
    $fakeModel->save();

    $approval = Approval::first();

    expect($approval->requestor)
        ->toBeInstanceOf(FakeUser::class)
        ->id->toBe($user->id)
        ->name->toBe($user->name);
});

test(description: 'scopeRequestedBy correctly filters approvals by requestor', closure: function (): void {
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

    $this->be($user1);
    $model1 = new FakeModel();
    $model1->name = 'Model by User 1';
    $model1->save();

    $this->be($user2);
    $model2 = new FakeModel();
    $model2->name = 'Model by User 2';
    $model2->save();

    $user1Approvals = Approval::requestedBy($user1)->get();

    expect($user1Approvals)->toHaveCount(1);
    expect($user1Approvals->first()->creator_id)->toBe($user1->id);
});

test(description: 'wasRequestedBy correctly identifies if a model requested the approval', closure: function (): void {
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
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();
    $approval->expiresIn(...$params);

    $expectedTime = $expectedTimeGenerator();

    expect($approval->fresh()->expires_at)->not->toBeNull();

    $difference = abs($approval->fresh()->expires_at->timestamp - $expectedTime->timestamp);

    $marginOfError = max(5, $expectedDuration * 0.03);
    expect($difference)->toBeLessThan($marginOfError);
})->with([
    [['minutes' => 30], fn () => now()->addMinutes(30), 30 * 60],
    [['hours' => 24], fn () => now()->addHours(24), 24 * 3600],
    [['days' => 7], fn () => now()->addDays(7), 7 * 86400],
    [['datetime' => now()->addWeek()], fn () => now()->addWeek(), 7 * 86400],
]);

test(description: 'throws exception when no expiration time is provided', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();

    expect(fn () => $approval->expiresIn())->toThrow(exception: InvalidArgumentException::class);
});

test(description: 'can check if an approval is expired', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();

    $approval->expiresIn(datetime: now()->subHour());
    expect($approval->isExpired())->toBeTrue();

    $approval->expiresIn(datetime: now()->addHour());
    expect($approval->isExpired())->toBeFalse();
});

test('can set automatic actions on expiration', function (string $method, ExpirationAction $expectedAction): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();

    $approval->expiresIn(hours: 1)->$method();

    expect($approval->fresh()->expiration_action)->toBe($expectedAction);
})->with([
    ['thenReject', ExpirationAction::Reject],
    ['thenPostpone', ExpirationAction::Postpone],
]);

test(description: 'can set custom action on expiration', closure: function (): void {
    FakeModel::create($this->fakeModelData);
    $approval = Approval::first();

    $approval->expiresIn(hours: 1)->thenCustom();

    expect($approval->fresh()->expiration_action)->toBe(expected: ExpirationAction::Custom);
});

test(description: 'can process expired approvals', closure: function (): void {
    FakeModel::create(['name' => 'Model 1', 'meta' => 'red']);
    $rejectionApproval = Approval::first();
    $rejectionApproval->expiresIn(datetime: now()->subHour())->thenReject();

    FakeModel::create(['name' => 'Model 2', 'meta' => 'blue']);
    $postponeApproval = Approval::orderByDesc(column: 'id')->first();
    $postponeApproval->expiresIn(datetime: now()->subHour())->thenPostpone();

    FakeModel::create(['name' => 'Model 3', 'meta' => 'green']);
    $futureApproval = Approval::orderByDesc(column: 'id')->first();
    $futureApproval->expiresIn(datetime: now()->addHour())->thenReject();

    Event::fake([
        ApprovalExpired::class,
        ModelRejected::class,
        ModelSetPending::class,
    ]);

    Approval::processExpired();

    expect($rejectionApproval->fresh()->state->value)
        ->toBe(expected: 'rejected')
        ->and($rejectionApproval->fresh()->actioned_at)
        ->not->toBeNull();

    expect($postponeApproval->fresh()->state->value)
        ->toBe(expected: 'pending')
        ->and($postponeApproval->fresh()->actioned_at)
        ->not->toBeNull();

    expect($futureApproval->fresh()->actioned_at)->toBeNull();

    Event::assertDispatched(event: ApprovalExpired::class, callback: 2);
    Event::assertDispatched(event: ModelRejected::class, callback: 1);
    Event::assertDispatched(event: ModelSetPending::class, callback: 1);
});

test(description: 'artisan command processes expired approvals', closure: function (): void {
    FakeModel::create([
        'name' => 'Test Model',
        'meta' => 'red',
    ]);

    $approval = Approval::first();
    $approval->expiresIn(datetime: now()->subHour())->thenReject();

    Event::fake([
        ApprovalExpired::class,
        ModelRejected::class,
    ]);

    $this->artisan(command: 'approval:process-expired')
        ->expectsOutput(output: '1 expired approval(s) processed successfully.')
        ->assertExitCode(exitCode: 0);

    expect($approval->fresh()->state->value)
        ->toBe(expected: 'rejected')
        ->and($approval->fresh()->actioned_at)
        ->not->toBeNull();

    Event::assertDispatched(event: ApprovalExpired::class, callback: 1);
    Event::assertDispatched(event: ModelRejected::class, callback: 1);
});

test(description: 'rollback is skipped when condition returns false', closure: function (): void {
    $fakeModel = new FakeModel();
    $fakeModel->name = 'Bob';
    $fakeModel->meta = 'green';
    $fakeModel->withoutApproval()->save();

    $fakeModel->fresh()->update(['name' => 'Chris']);
    $fakeModel->fresh()->approvals()->first()->approve();

    $approval = $fakeModel->fresh()->approvals()->first();
    $approval->rollback(condition: fn (): false => false);

    expect($approval->fresh())
        ->state->toBe(expected: ApprovalStatus::Approved)
        ->rolled_back_at->toBeNull();
});

test(description: 'rollback throws exception when approval is not approved', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();

    expect(fn () => $approval->rollback())
        ->toThrow(exception: Exception::class, exceptionMessage: 'Cannot rollback an Approval that has not been approved.');
});

test(description: 'processExpired handles approval with null expiration action', closure: function (): void {
    FakeModel::create(['name' => 'Model 1', 'meta' => 'red']);
    $approval = Approval::first();
    $approval->expiresIn(datetime: now()->subHour());

    Event::fake([ApprovalExpired::class]);

    Approval::processExpired();

    expect($approval->fresh())
        ->state->toBe(expected: ApprovalStatus::Pending)
        ->actioned_at->not->toBeNull();

    Event::assertDispatched(event: ApprovalExpired::class, callback: 1);
});

test(description: 'processExpired handles approval with custom expiration action', closure: function (): void {
    FakeModel::create(['name' => 'Model 1', 'meta' => 'red']);
    $approval = Approval::first();
    $approval->expiresIn(datetime: now()->subHour())->thenCustom();

    Event::fake([ApprovalExpired::class]);

    Approval::processExpired();

    expect($approval->fresh())
        ->state->toBe(expected: ApprovalStatus::Pending)
        ->actioned_at->not->toBeNull()
        ->expiration_action->toBe(expected: ExpirationAction::Custom);

    Event::assertDispatched(event: ApprovalExpired::class, callback: 1);
});

test(description: 'processExpired continues processing when an approval throws an exception', closure: function (): void {
    FakeModel::create(['name' => 'Model 1', 'meta' => 'red']);
    $badApproval = Approval::first();
    $badApproval->expiresIn(datetime: now()->subHour());

    FakeModel::create(['name' => 'Model 2', 'meta' => 'blue']);
    $goodApproval = Approval::orderByDesc(column: 'id')->first();
    $goodApproval->expiresIn(datetime: now()->subHour());

    $thrown = false;

    Event::listen(ApprovalExpired::class, function () use (&$thrown): void {
        if (! $thrown) {
            $thrown = true;

            throw new RuntimeException('Simulated failure');
        }
    });

    $count = Approval::processExpired();

    expect($goodApproval->fresh()->actioned_at)->not->toBeNull()
        ->and($count)->toBeGreaterThanOrEqual(expected: 1);
});

test(description: 'creator relationship returns the authenticated user', closure: function (): void {
    $user = FakeUser::create($this->fakeUserData);
    $this->be($user);

    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();

    expect($approval->creator)
        ->toBeInstanceOf(FakeUser::class)
        ->id->toBe($user->id);
});

test(description: 'getState returns the standard state when no custom state is set', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $approval = Approval::first();

    expect($approval->getState())->toBe(expected: 'pending');
});
