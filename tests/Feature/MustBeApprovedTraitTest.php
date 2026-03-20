<?php

declare(strict_types=1);

use Cjmellor\Approval\Concerns\MustBeApproved;
use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Events\ApprovalCreated;
use Cjmellor\Approval\Models\Approval;
use Cjmellor\Approval\Tests\Models\FakeModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\User;

it(description: 'stores the data correctly in the database')
    ->defer(
        fn (): Approval => Approval::create($this->approvalData)
    )->assertDatabaseHas(Approval::class, [
        'approvalable_type' => User::class,
        'approvalable_id' => 1,
        'state' => ApprovalStatus::Pending,
    ]);

test(description: 'an ApprovalCreated event is dispatched when a model is created with MustBeApproved trait set',
    closure: function (): void {
        Event::fake(eventsToFake: ApprovalCreated::class);

        $user = $this->createAndAuthenticateUser();

        FakeModel::create($this->fakeModelData);

        Event::assertDispatched(fn (ApprovalCreated $event): bool => $event->approval->new_data->toArray() === $this->fakeModelData
            && $event->user->is($user));
    });

test(description: 'an approvals model is created when a model is created with MustBeApproved trait set')
    ->defer(callable: fn () => FakeModel::create($this->fakeModelData))
    ->assertDatabaseHas(Approval::class, [
        'new_data' => json_encode([
            'name' => 'Chris',
            'meta' => 'red',
        ]),
        'original_data' => json_encode([]),
    ])
    ->assertDatabaseMissing('fake_models', [
        'name' => 'Chris',
        'meta' => 'red',
    ]);

test(description: 'check the creator field if user is authenticated', closure: function (): void {
    $user = $this->createAndAuthenticateUser();

    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->save();

    $this->assertDatabaseHas(
        table: Approval::class,
        data: [
            'creator_id' => $user->id,
            'creator_type' => User::class,
        ]
    );
});

test(description: 'check the creator field if no user is authenticated', closure: function (): void {
    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->save();

    $this->assertDatabaseHas(
        table: Approval::class,
        data: [
            'creator_id' => null,
            'creator_type' => null,
        ]
    );
});

test(description: 'a model is added when the "withoutApproval()" method is used', closure: function (): void {
    $fakeModel = new FakeModel();

    $fakeModel->name = 'Bob';
    $fakeModel->meta = 'green';

    $fakeModel->withoutApproval()->save();

    $this->assertDatabaseHas(table: FakeModel::class, data: [
        'name' => 'Bob',
        'meta' => 'green',
    ]);

    $this->assertDatabaseMissing('approvals', [
        'new_data' => json_encode([
            'name' => 'Bob',
        ]),
    ]);
});

test(description: 'an ApprovalCreated event is dispatched when a model is updated with MustBeApproved trait set',
    closure: function (): void {
        Event::fake([ApprovalCreated::class]);

        $user = $this->createAndAuthenticateUser();

        $model = new FakeModel();

        $model->name = 'Bob';
        $model->meta = 'green';

        $model->withoutApproval()->save();

        $model->fresh()->update(['name' => 'Chris']);

        Event::assertDispatched(fn (ApprovalCreated $event): bool => $event->approval->new_data->toArray() === ['name' => 'Chris']
            && $event->user->is(auth()->user()));
    });

test(description: 'an approval model cannot be duplicated', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode($this->fakeModelData),
    ]);

    FakeModel::create($this->fakeModelData);

    $this->assertDatabaseCount('approvals', 1);
});

test(description: 'a Model is added to the corresponding table when approved', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode($this->fakeModelData),
    ]);

    $this->assertDatabaseMissing('fake_models', $this->fakeModelData);

    Approval::first()->approve();

    $this->assertDatabaseHas(table: FakeModel::class, data: $this->fakeModelData);
});

test(description: 'A Model that is only being updated, is persisted correctly to the database', closure: function (): void {
    (new FakeModel($this->fakeModelData))
        ->withoutApproval()
        ->save();

    FakeModel::first()
        ->update(['name' => 'Bob']);

    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode([
            'name' => 'Bob',
        ]),
        'original_data' => json_encode([
            'name' => 'Chris',
        ]),
    ]);

    Approval::first()->approve();

    $this->assertDatabaseHas(table: FakeModel::class, data: [
        'name' => 'Bob',
        'meta' => 'red',
    ]);
});

test(description: 'a Model cannot be persisted when given a flag', closure: function (): void {
    FakeModel::create($this->fakeModelData);

    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode($this->fakeModelData),
    ]);

    Approval::first()->approve(false);

    $this->assertDatabaseCount('fake_models', 0);
});

test(description: 'an approvals model is created when a model is created with MustBeApproved trait set and has the approvalInclude array set',
    closure: function (): void {
        $model = new class() extends Model
        {
            use MustBeApproved;

            public $timestamps = false;

            protected $table = 'fake_models';

            protected array $approvalAttributes = ['name'];

            protected $guarded = [];
        };

        $model->create([
            'name' => 'Neo',
            'meta' => 'blue',
        ]);

        $this->assertDatabaseHas(table: Approval::class, data: [
            'new_data' => json_encode(['name' => 'Neo']),
            'original_data' => json_encode([]),
        ]);

        $this->assertDatabaseHas(table: FakeModel::class, data: [
            'meta' => 'blue',
        ]);
    });

test(description: 'approve a attribute of the type Array', closure: function (): void {
    Schema::create('fake_models_with_array', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('meta')->nullable();
        $table->json('data')->nullable();
        $table->foreignId('user_id')->nullable();
    });

    $model = new class() extends Model
    {
        use MustBeApproved;

        public $timestamps = false;

        protected $table = 'fake_models_with_array';

        protected $guarded = [];

        protected $casts = ['data' => 'array'];
    };

    $model->create([
        'name' => 'Neo',
        'data' => ['foo', 'bar'],
    ]);

    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode([
            'name' => 'Neo',
            'data' => ['foo', 'bar'],
        ]),
        'original_data' => json_encode([]),
    ]);

    $this->assertDatabaseCount('fake_models_with_array', 0);

    Approval::first()->approve();

    $this->assertDatabaseCount('fake_models_with_array', 1);

    $this->assertDatabaseHas(table: 'fake_models_with_array', data: [
        'name' => 'Neo',
        'data' => json_encode(['foo', 'bar']),
    ]);

    $modelFromDatabase = $model->firstWhere('name', 'Neo');

    expect($modelFromDatabase->data)
        ->toBe(['foo', 'bar']);
});

test(description: 'a Model can be rolled back when the data contains JSON fields', closure: function (): void {
    Schema::create('posts', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->string('content');
        $table->json('config');
        $table->foreignId('user_id')->nullable();
        $table->timestamps();
    });

    $model = new class() extends Model
    {
        use MustBeApproved;

        protected $table = 'posts';

        protected $guarded = [];

        protected $casts = ['config' => 'json'];
    };

    $model->create([
        'title' => 'My First Post',
        'content' => 'This is my first post',
        'config' => ['checked' => true],
    ]);

    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode([
            'title' => 'My First Post',
            'content' => 'This is my first post',
            'config' => ['checked' => true],
        ]),
        'original_data' => json_encode([]),
    ]);

    $this->assertDatabaseCount('posts', 0);

    Approval::first()->approve();

    $this->assertDatabaseCount('posts', 1);

    $this->assertDatabaseHas(table: 'posts', data: [
        'title' => 'My First Post',
        'content' => 'This is my first post',
        'config' => json_encode(['checked' => true]),
    ]);

    $modelFromDatabase = $model->firstWhere('title', 'My First Post');

    expect($modelFromDatabase->config)
        ->toBe(['checked' => true]);
});

test('the foreign key is extracted from the payload and stored in a separate column', function (): void {
    $model = new class() extends Model
    {
        use MustBeApproved;

        public $timestamps = false;

        protected $table = 'fake_models';

        protected $guarded = [];

        public function getApprovalForeignKeyName(): string
        {
            return 'user_id';
        }
    };

    $model->create([
        'name' => 'Neo',
    ]);

    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode(['name' => 'Neo']),
        'original_data' => json_encode([]),
        'foreign_key' => null,
    ]);
});

test(description: 'approve a model with nested array attributes', closure: function (): void {
    Schema::create('models_with_nested_arrays', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->json('settings')->nullable();
        $table->json('metadata')->nullable();
    });

    $model = new class() extends Model
    {
        use MustBeApproved;

        public $timestamps = false;

        protected $table = 'models_with_nested_arrays';

        protected $guarded = [];

        protected $casts = [
            'settings' => 'array',
            'metadata' => 'array',
        ];
    };

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

    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode($testData),
        'original_data' => json_encode([]),
    ]);

    expect($model->count())->toBe(0);

    Approval::first()->approve();

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

test(description: 'callCastAttribute casts a non-array string value through the model cast', closure: function (): void {
    Schema::create('cast_test_models', function (Blueprint $table): void {
        $table->id();
        $table->json('data')->nullable();
        $table->foreignId('user_id')->nullable();
    });

    $model = new class() extends Model
    {
        use MustBeApproved;

        public $timestamps = false;

        protected $table = 'cast_test_models';

        protected $guarded = [];

        protected $casts = ['data' => 'array'];
    };

    $result = $model->callCastAttribute('data', json_encode(['foo' => 'bar']));

    expect($result)->toBe(['foo' => 'bar']);
});
