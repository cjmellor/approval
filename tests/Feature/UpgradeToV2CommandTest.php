<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Helper functions for test setup
function createV1Schema(): void
{
    Schema::create('approvals', function (Blueprint $table) {
        $table->id();
        $table->nullableMorphs('approvalable');
        $table->enum('state', ['pending', 'approved', 'rejected'])->default('pending');
        $table->json('new_data')->nullable();
        $table->json('original_data')->nullable();
        $table->timestamp('rolled_back_at')->nullable();
        $table->foreignId('audited_by')->nullable()->constrained('users');
        $table->unsignedBigInteger('foreign_key')->nullable();
        $table->nullableMorphs('creator');
        $table->timestamp('expires_at')->nullable();
        $table->string('expiration_action')->nullable();
        $table->timestamp('actioned_at')->nullable();
        $table->foreignId('actioned_by')->nullable()->constrained('users');
        $table->timestamps();
    });
}

function seedV1ApprovalData(): int
{
    // Use individual inserts instead of bulk insert to handle different column sets

    // Pending approval
    DB::table('approvals')->insert([
        'approvalable_type' => 'App\\Models\\Test',
        'approvalable_id' => 1,
        'state' => 'pending',
        'new_data' => json_encode(['name' => 'Test Pending']),
        'original_data' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Approved approval
    DB::table('approvals')->insert([
        'approvalable_type' => 'App\\Models\\Test',
        'approvalable_id' => 2,
        'state' => 'approved',
        'new_data' => json_encode(['name' => 'Test Approved']),
        'original_data' => json_encode(['name' => 'Old Name']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Rejected approval
    DB::table('approvals')->insert([
        'approvalable_type' => 'App\\Models\\Test',
        'approvalable_id' => 3,
        'state' => 'rejected',
        'new_data' => json_encode(['name' => 'Test Rejected']),
        'original_data' => json_encode(['name' => 'Original Name']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Approval with expiration setting
    DB::table('approvals')->insert([
        'approvalable_type' => 'App\\Models\\Test',
        'approvalable_id' => 4,
        'state' => 'pending',
        'new_data' => json_encode(['name' => 'Test With Expiration']),
        'original_data' => json_encode([]),
        'expires_at' => now()->addDay(),
        'expiration_action' => 'reject',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Approval with UTF-8 multibyte characters
    DB::table('approvals')->insert([
        'approvalable_type' => 'App\\Models\\Test',
        'approvalable_id' => 5,
        'state' => 'pending',
        'new_data' => json_encode(['name' => 'Test with UTF-8: 你好, こんにちは, 안녕하세요']),
        'original_data' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Approval with large dataset
    DB::table('approvals')->insert([
        'approvalable_type' => 'App\\Models\\Test',
        'approvalable_id' => 6,
        'state' => 'pending',
        'new_data' => json_encode(array_fill(0, 100, 'Data entry for testing large datasets')),
        'original_data' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Return count for verification
    return DB::table('approvals')->count();
}

function setupV1Environment(): int
{
    // Drop approvals table if it exists
    Schema::dropIfExists('approvals');

    // Create v1 schema
    createV1Schema();

    // Seed with test data and return the count
    return seedV1ApprovalData();
}

// Setup our v1 environment for all tests in this file
beforeEach(function () {
    $this->approvalCount = setupV1Environment();
});

test('upgrade successfully migrates schema with confirmation', function () {
    // Verify we're starting with v1 schema
    expect(Schema::hasColumn('approvals', 'custom_state'))->toBeFalse('Test should start with v1 schema');

    // Mock console confirmation to return true
    $this->artisan('approval:upgrade-to-v2')
        ->expectsConfirmation('Have you backed up your database? This operation modifies schema.', 'yes')
        ->expectsOutput('✅ Database schema successfully upgraded to v2')
        ->assertExitCode(0);

    // Verify schema was upgraded
    expect(Schema::hasColumn('approvals', 'custom_state'))->toBeTrue('Column custom_state should exist after upgrade');

    // Verify all data is intact
    expect(DB::table('approvals')->count())->toBe($this->approvalCount, 'All records should be preserved');

    // Verify data integrity for a specific record
    $record = DB::table('approvals')->where('approvalable_id', 5)->first();
    expect($record)->not->toBeNull();

    $data = json_decode($record->new_data, true);
    expect($data['name'])->toBe('Test with UTF-8: 你好, こんにちは, 안녕하세요', 'Multibyte data should be preserved');
});

test('upgrade detects already upgraded schema', function () {
    // First run the upgrade
    $this->artisan('approval:upgrade-to-v2')
        ->expectsConfirmation('Have you backed up your database? This operation modifies schema.', 'yes');

    // Run again to test detection
    $this->artisan('approval:upgrade-to-v2')
        ->expectsOutput('✅ Your database is already using the v2 schema.')
        ->assertExitCode(0);
});

test('upgrade aborts without confirmation', function () {
    // Reset to v1 schema
    Schema::dropIfExists('approvals');
    createV1Schema();
    seedV1ApprovalData();

    // Run upgrade but answer "no" to backup confirmation
    $this->artisan('approval:upgrade-to-v2')
        ->expectsConfirmation('Have you backed up your database? This operation modifies schema.', 'no')
        ->expectsOutput('Please backup your database before proceeding.')
        ->assertExitCode(1);

    // Verify schema was not changed
    expect(Schema::hasColumn('approvals',
        'custom_state'))->toBeFalse('Schema should not be modified without confirmation');
});

test('upgrade preserves all approval states', function () {
    // Get counts by state before upgrade
    $pendingCount = DB::table('approvals')->where('state', 'pending')->count();
    $approvedCount = DB::table('approvals')->where('state', 'approved')->count();
    $rejectedCount = DB::table('approvals')->where('state', 'rejected')->count();

    // Run upgrade
    $this->artisan('approval:upgrade-to-v2')
        ->expectsConfirmation('Have you backed up your database? This operation modifies schema.', 'yes');

    // Verify counts remain the same after upgrade
    expect(DB::table('approvals')->where('state', 'pending')->count())
        ->toBe($pendingCount, 'Pending approvals count should be preserved');

    expect(DB::table('approvals')->where('state', 'approved')->count())
        ->toBe($approvedCount, 'Approved approvals count should be preserved');

    expect(DB::table('approvals')->where('state', 'rejected')->count())
        ->toBe($rejectedCount, 'Rejected approvals count should be preserved');
});
