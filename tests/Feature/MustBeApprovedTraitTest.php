<?php

use Approval\Approval\Concerns\MustBeApproved;
use Approval\Approval\Enums\ApprovalStatus;
use Approval\Approval\Models\Approval;
use Approval\Approval\Tests\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it(description: 'stores the data correctly in the database')
    ->defer(
        fn(): Approval => Approval::create($this->approvalData)
    )->assertDatabaseHas('approvals', [
        'approvalable_type' => Comment::class,
        'approvalable_id' => 1,
        'state' => ApprovalStatus::Pending,
    ]);

test(description: 'an approvals model is created when a model is created with MustBeApproved trait set')
    // create a fake model
    ->defer(callable: fn() => Comment::create($this->fakeModelData))
    // check it has been put in the approvals' table before the fake_models table
    ->assertDatabaseHas('approvals', [
        'new_data' => json_encode([
            'comment' => 'I have a radio in my car',
        ]),
        'original_data' => json_encode([]),
    ])
    // this should be blank as the trait has intervened
    ->assertDatabaseMissing('comments', [
        'comment' => 'I have a radio in my car',
    ]);

test(description: 'a model is added when the "withoutApproval()" method is used', closure: function () {
    // build a query
    $fakeModel = new Comment();

    $fakeModel->comment = 'I like pie';

    // save the model, bypassing approval
    $fakeModel->withoutApproval()->save();

    $this->assertDatabaseHas('comments', [
        'comment' => 'I like pie',
    ]);

    $this->assertDatabaseMissing('approvals', [
        'new_data' => json_encode([
            'comment' => 'I like pie',
        ]),
    ]);
});

test(description: 'an approval model cannot be duplicated', closure: function () {
    // create a fake model with data
    Comment::create($this->fakeModelData);

    // check it was added to the db
    $this->assertDatabaseHas('approvals', [
        'new_data' => json_encode($this->fakeModelData),
    ]);

    // add another model with the same data...
    Comment::create($this->fakeModelData);

    // as it is a duplicate, it should not be added to the DB
    $this->assertDatabaseCount('approvals', 1);
});

test(description: 'a Model is added to the corresponding table when approved', closure: function () {
    // create a fake model with data
    Comment::create($this->fakeModelData);

    // check it was added to the db
    $this->assertDatabaseHas('approvals', [
        'new_data' => json_encode($this->fakeModelData),
    ]);

    // check that it hasn't been added to the fake_models table
    $this->assertDatabaseMissing('comments', $this->fakeModelData);

    // approve the model
    Approval::first()->approve();

    // check it was added to the fake_models table
    $this->assertDatabaseHas('comments', $this->fakeModelData);
});

test(description: 'A Model that is only being updated, is persisted correctly to the database', closure: function () {
    // create a fake model with data
    (new Comment($this->fakeModelData))
        ->withoutApproval()
        ->save();

    // update the model with new data
    Comment::first()
        ->update(['comment' => 'I like pie']);

    // check it was added to the db
    $this->assertDatabaseHas('approvals', [
        'new_data' => json_encode([
            'comment' => 'I like pie',
        ]),
        'original_data' => json_encode([
            'comment' => 'I have a radio in my car',
        ]),
    ]);

    // approve the model
    Approval::first()->approve();

    // check the fake_models table was updated correctly
    $this->assertDatabaseHas('comments', [
        'comment' => 'I like pie',
    ]);
});

test(description: 'a Model cannot be persisted when given a flag', closure: function () {
    // create a fake model with data
    Comment::create($this->fakeModelData);

    // check it was added to the db
    $this->assertDatabaseHas('approvals', [
        'new_data' => json_encode($this->fakeModelData),
    ]);

    // approve the model
    Approval::first()->approve(false);

    // check it was added to the fake_models table
    $this->assertDatabaseCount('comments', 0);
});

test(description: 'an approvals model is created when a model is created with MustBeApproved trait set and has the approvalInclude array set', closure: function () {
    $model = new class extends Model {
        use MustBeApproved;

        protected $table = 'comments';

        protected array $approvalAttributes = ['comment'];

        protected $guarded = [];
    };

    // create a model
    $model->create(['comment' => 'Hello']);

    $this->assertDatabaseHas(table: Approval::class, data: [
        'new_data' => json_encode(['comment' => 'Hello']),
        'original_data' => json_encode([]),
    ]);

    $this->assertDatabaseMissing(table: Comment::class, data: [
        'comment' => 'Hello',
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

    $model = new class extends Model {
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
            'data' => json_encode(['foo', 'bar']),
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

    $model = new class extends Model {
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
    $model = new class extends Model {
        use MustBeApproved;

        protected $table = 'comments';

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
