<?php

use Cjmellor\Approval\Concerns\MustBeApproved;
use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Events\ApprovalCreated;
use Cjmellor\Approval\Models\Approval;
use Cjmellor\Approval\Tests\Models\FakeModel;
use Cjmellor\Approval\Tests\Models\FakeUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

it(description: 'stores the data correctly in the database')
    ->defer(
        fn (): Approval => Approval::create($this->approvalData)
    )->assertDatabaseHas('approvals', [
        'approvalable_type' => FakeModel::class,
        'approvalable_id' => 1,
        'state' => ApprovalStatus::Pending,
    ]);

test(description: 'an ApprovalCreated event is dispatched when a model is created with MustBeApproved trait set', closure: function () {
    // Arrange
    Event::fake([ApprovalCreated::class]);
    $user = FakeUser::create($this->fakeUserData);
    $this->be($user);

    // Act
    FakeModel::create($this->fakeModelData);

    // Assert
    Event::assertDispatched(function (ApprovalCreated $event) {
        return $event->approval->new_data->toArray() === $this->fakeModelData
            && $event->user->id === auth()->id();
    });
});

test(description: 'an approvals model is created when a model is created with MustBeApproved trait set')
    // create a fake model
    ->defer(callable: fn () => FakeModel::create($this->fakeModelData))
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

test(description: 'check the creator field if user is authenticated', closure: function () {
    $user = FakeUser::create($this->fakeUserData);
    $this->be($user);

    $fakeModel = new FakeModel();
    $fakeModel->name = 'Bob';
    $fakeModel->save();

    $this->assertDatabaseHas(
        table: 'approvals',
        data: [
            'creator_id' => 1,
            'creator_type' => FakeUser::class,
        ]
    );
});

test(description: 'check the creator field if no user is authenticated', closure: function () {
    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->save();

    $this->assertDatabaseHas(
        table: 'approvals',
        data: [
            'creator_id' => null,
            'creator_type' => null
        ]
    );
});

test(description: 'a model is added when the "withoutApproval()" method is used', closure: function () {
    // build a query
    $fakeModel = new FakeModel;

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

test(description: 'an ApprovalCreated event is dispatched when a model is updated with MustBeApproved trait set', closure: function () {
    // Arrange
    Event::fake([ApprovalCreated::class]);

    $user = FakeUser::create($this->fakeUserData);
    $this->be($user);

    $model = new FakeModel;

    $model->name = 'Bob';
    $model->meta = 'green';

    $model->withoutApproval()->save();

    // Act
    $model->fresh()->update(['name' => 'Chris']);

    // Assert
    Event::assertDispatched(function (ApprovalCreated $event) {
        return $event->approval->new_data->toArray() === ['name' => 'Chris']
            && $event->user->id === auth()->id();
    });
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

    // check that it hasn't been added to the fake_models table
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
    $this->assertDatabaseCount('fake_models', 0);
});

test(description: 'an approvals model is created when a model is created with MustBeApproved trait set and has the approvalInclude array set', closure: function () {
    $model = new class extends Model
    {
        use MustBeApproved;

        protected $table = 'fake_models';

        protected array $approvalAttributes = ['name'];

        protected $guarded = [];

        public $timestamps = false;
    };

    // create a model
    $model->create([
        'name' => 'Neo',
        'meta' => 'blue',
    ]);

    // there should only be an approval model for the 'name' attribute, the 'meta' should be stored
    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode(['name' => 'Neo']),
        'original_data' => json_encode([]),
    ]);

    // Since the 'meta' attribute was not included in approvalInclude, it should be stored
    $this->assertDatabaseHas(table: FakeModel::class, data: [
        'meta' => 'blue',
    ]);
});

test(description: 'approve a attribute of the type Array', closure: function () {
    Schema::create('fake_models_with_array', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('meta')->nullable();
        $table->json('data')->nullable();
        $table->foreignId('user_id')->nullable();
    });

    $model = new class extends Model
    {
        use MustBeApproved;

        protected $table = 'fake_models_with_array';

        protected $guarded = [];

        public $timestamps = false;

        protected $casts = ['data' => 'array'];
    };

    // create a model
    $model->create([
        'name' => 'Neo',
        'data' => ['foo', 'bar'],
    ]);

    // check if the data is stored correctly in the approval table
    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode([
            'name' => 'Neo',
            'data' => ['foo', 'bar'],
        ]),
        'original_data' => json_encode([]),
    ]);

    // nothing should be in the 'fake_models_with_array' table
    $this->assertDatabaseCount('fake_models_with_array', 0);

    // approve the model
    Approval::first()->approve();

    // after approval, there should be in an entry in the 'fake_models_with_array' table
    $this->assertDatabaseCount('fake_models_with_array', 1);

    // After Approval, the contents of the database should look like this
    $this->assertDatabaseHas(table: 'fake_models_with_array', data: [
        'name' => 'Neo',
        'data' => json_encode(['foo', 'bar']),
    ]);

    // double-check the model
    $modelFromDatabase = $model->firstWhere('name', 'Neo');

    expect($modelFromDatabase->data)
        ->toBe(['foo', 'bar']);
});

test(description: 'a Model can be rolled back when the data contains JSON fields', closure: function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('content');
        $table->json('config');
        $table->foreignId('user_id')->nullable();
        $table->timestamps();
    });

    $model = new class extends Model
    {
        use MustBeApproved;

        protected $table = 'posts';

        protected $guarded = [];

        protected $casts = ['config' => 'json'];
    };

    // create a model
    $model->create([
        'title' => 'My First Post',
        'content' => 'This is my first post',
        'config' => ['checked' => true],
    ]);

    // check if the data is stored correctly in the approval table
    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode([
            'title' => 'My First Post',
            'content' => 'This is my first post',
            'config' => ['checked' => true],
        ]),
        'original_data' => json_encode([]),
    ]);

    // nothing should be in the 'posts' table
    $this->assertDatabaseCount('posts', 0);

    // approve the model
    Approval::first()->approve();

    // after approval, there should be in an entry in the 'posts' table
    $this->assertDatabaseCount('posts', 1);

    // After Approval, the contents of the database should look like this
    $this->assertDatabaseHas(table: 'posts', data: [
        'title' => 'My First Post',
        'content' => 'This is my first post',
        'config' => json_encode(['checked' => true]),
    ]);

    // double-check the model
    $modelFromDatabase = $model->firstWhere('title', 'My First Post');

    expect($modelFromDatabase->config)
        ->toBe(['checked' => true]);
});

test('the foreign key is extracted from the payload and stored in a separate column', function () {
    $model = new class extends Model
    {
        use MustBeApproved;

        protected $table = 'fake_models';

        protected $guarded = [];

        public $timestamps = false;

        public function getApprovalForeignKeyName(): string
        {
            return 'user_id';
        }
    };

    // create a model
    $model->create([
        'name' => 'Neo',
    ]);

    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode(['name' => 'Neo']),
        'original_data' => json_encode([]),
        'foreign_key' => null,
    ]);
});

test(description: 'approve a model with nested array attributes', closure: function () {
    Schema::create('models_with_nested_arrays', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->json('settings')->nullable();
        $table->json('metadata')->nullable();
    });

    $model = new class extends Model
    {
        use MustBeApproved;

        protected $table = 'models_with_nested_arrays';

        protected $guarded = [];

        public $timestamps = false;

        protected $casts = [
            'settings' => 'array',
            'metadata' => 'array',
        ];
    };

    // Create a model with complex nested array data
    $testData = [
        'name' => 'Test Model',
        'settings' => [
            'preferences' => [
                'theme' => 'dark',
                'notifications' => true,
            ],
            'features' => ['feature1', 'feature2'],
        ],
        'metadata' => [
            'tags' => ['important', 'test'],
            'version' => 2,
        ],
    ];

    $model->create($testData);

    // Verify data in approval table using database assertion instead of model property
    $this->assertDatabaseHas(Approval::class, [
        'new_data' => json_encode($testData),
        'original_data' => json_encode([]),
    ]);

    // Nothing should be in the main table yet
    expect($model->count())->toBe(0);

    // Approve the model
    Approval::first()->approve();

    // Verify the data after approval
    $savedModel = $model->first();

    expect($savedModel)
        ->name->toBe('Test Model')
        ->settings->toBeArray()
        ->metadata->toBeArray()
        ->and($savedModel->settings['preferences']['theme'])->toBe('dark')
        ->and($savedModel->settings['preferences']['notifications'])->toBeTrue()
        ->and($savedModel->settings['features'])->toMatchArray(['feature1', 'feature2'])
        ->and($savedModel->metadata['tags'])->toMatchArray(['important', 'test'])
        ->and($savedModel->metadata['version'])->toBe(2);
});
