<?php

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Models\Approval;
use Cjmellor\Approval\Tests\Models\FakeModel;

beforeEach(closure: function (): void {
    $this->approvalData = [
        'approvalable_type' => 'App\Models\FakeModel',
        'approvalable_id' => 1,
        'state' => ApprovalStatus::Pending,
        'new_data' => json_encode(['name' => 'Chris']),
        'original_data' => json_encode(['name' => 'Bob']),
    ];

    $this->fakeModelData = [
        'name' => 'Chris',
        'meta' => 'red',
    ];
});

it(description: 'stores the data correctly in the database')
    ->tap(
        fn(): Approval => Approval::create($this->approvalData)
    )->assertDatabaseHas('approvals', [
        'approvalable_type' => 'App\Models\FakeModel',
        'approvalable_id' => 1,
        'state' => ApprovalStatus::Pending,
    ]);

test(description: 'an approvals model is created when a model is created with MustBeApproved trait set')
    // create a fake model
    ->tap(callable: fn() => FakeModel::create($this->fakeModelData))
    // check it has been put in the approvals' table before the fake_models table
    ->assertDatabaseHas('approvals', [
        'new_data' => json_encode([
            'name' => 'Chris',
            'meta' => 'red',
        ]),
        'original_data' => json_encode([]),
    ])
    // this should be blank as the trait has intervened
    ->assertDatabaseMissing('fake_models', [
        'name' => 'Chris',
        'meta' => 'red',
    ]);

test(description: 'a model is added when the "withoutApproval()" method is used', closure: function () {
    // build a query
    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->meta = 'green';

    // save the model, bypassing approval
    $fakeModel->withoutApproval()->save();

    $this->assertDatabaseHas('fake_models', [
        'name' => 'Bob',
        'meta' => 'green',
    ]);

    $this->assertDatabaseMissing('approvals', [
        'new_data' => json_encode([
            'name' => 'Bob',
        ]),
    ]);
});

test(description: 'an approval model cannot be duplicated', closure: function () {
    // create a fake model with data
    FakeModel::create($this->fakeModelData);

    // check it was added to the db
    $this->assertDatabaseHas('approvals', [
        'new_data' => json_encode($this->fakeModelData),
    ]);

    // add another model with the same data...
    FakeModel::create($this->fakeModelData);

    // as it is a duplicate, it should not be added to the DB
    $this->assertDatabaseCount('approvals', 1);
});

test(description: 'a Model is added to the corresponding table when approved', closure: function () {
    // create a fake model with data
    FakeModel::create($this->fakeModelData);

    // check it was added to the db
    $this->assertDatabaseHas('approvals', [
        'new_data' => json_encode($this->fakeModelData),
    ]);

    // sanity check that is hasn't been added to the fake_models table
    $this->assertDatabaseMissing('fake_models', $this->fakeModelData);

    // approve the model
    Approval::first()->approve();

    // check it was added to the fake_models table
    $this->assertDatabaseHas('fake_models', $this->fakeModelData);
});

test(description: 'A Model that is only being updated, is persisted correctly to the database', closure: function () {
    // create a fake model with data
    (new FakeModel($this->fakeModelData))
        ->withoutApproval()
        ->save();

    // update the model with new data
    FakeModel::first()
        ->update(['name' => 'Bob']);

    // check it was added to the db
    $this->assertDatabaseHas('approvals', [
        'new_data' => json_encode([
            'name' => 'Bob',
        ]),
        'original_data' => json_encode([
            'name' => 'Chris',
        ]),
    ]);

    // approve the model
    Approval::first()->approve();

    // check the fake_models table was updated correctly
    $this->assertDatabaseHas('fake_models', [
        'name' => 'Bob',
        'meta' => 'red',
    ]);
});

test(description: 'a Model cannot be persisted when given a flag', closure: function () {
    // create a fake model with data
    FakeModel::create($this->fakeModelData);

    // check it was added to the db
    $this->assertDatabaseHas('approvals', [
        'new_data' => json_encode($this->fakeModelData),
    ]);

    // approve the model
    Approval::first()->approve(false);

    // check it was added to the fake_models table
    $this->assertDatabaseEmpty('fake_models');
});
