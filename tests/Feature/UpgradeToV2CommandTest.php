<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Setup our v1 environment for all tests in this file
beforeEach(function (): void {
    // Drop approvals table if it exists
    Schema::dropIfExists(table: 'approvals');

    // Create v1 schema
    Schema::create(table: 'approvals', callback: function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs(name: 'approvalable');
        $table->enum(column: 'state', allowed: ['pending', 'approved', 'rejected'])->default(value: 'pending');
        $table->json(column: 'new_data')->nullable();
        $table->json(column: 'original_data')->nullable();
        $table->timestamp(column: 'rolled_back_at')->nullable();
        $table->foreignId(column: 'audited_by')->nullable()->constrained(table: 'users');
        $table->unsignedBigInteger(column: 'foreign_key')->nullable();
        $table->nullableMorphs(name: 'creator');
        $table->timestamp(column: 'expires_at')->nullable();
        $table->string(column: 'expiration_action')->nullable();
        $table->timestamp(column: 'actioned_at')->nullable();
        $table->foreignId(column: 'actioned_by')->nullable()->constrained(table: 'users');
        $table->timestamps();
    });

    // Seed with test data
    seedTestApprovals();

    // Store count for verification
    $this->approvalCount = DB::table(table: 'approvals')->count();
});

// Helper method to seed test data
function seedTestApprovals(): void
{
    // Pending approval
    DB::table(table: 'approvals')->insert([
        'approvalable_type' => 'App\\Models\\Test',
        'approvalable_id' => 1,
        'state' => 'pending',
        'new_data' => json_encode(['name' => 'Test Pending']),
        'original_data' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Approved approval
    DB::table(table: 'approvals')->insert([
        'approvalable_type' => 'App\\Models\\Test',
        'approvalable_id' => 2,
        'state' => 'approved',
        'new_data' => json_encode(['name' => 'Test Approved']),
        'original_data' => json_encode(['name' => 'Old Name']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Rejected approval
    DB::table(table: 'approvals')->insert([
        'approvalable_type' => 'App\\Models\\Test',
        'approvalable_id' => 3,
        'state' => 'rejected',
        'new_data' => json_encode(['name' => 'Test Rejected']),
        'original_data' => json_encode(['name' => 'Original Name']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Approval with expiration setting
    DB::table(table: 'approvals')->insert([
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
    DB::table(table: 'approvals')->insert([
        'approvalable_type' => 'App\\Models\\Test',
        'approvalable_id' => 5,
        'state' => 'pending',
        'new_data' => json_encode(['name' => 'Test with UTF-8: 你好, こんにちは, 안녕하세요']),
        'original_data' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Approval with large dataset
    DB::table(table: 'approvals')->insert(values: [
        'approvalable_type' => 'App\\Models\\Test',
        'approvalable_id' => 6,
        'state' => 'pending',
        'new_data' => json_encode(value: array_fill(
            start_index: 0,
            count: 100,
            value: 'Data entry for testing large datasets')
        ),
        'original_data' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test(description: 'upgrade successfully migrates schema with confirmation', closure: function (): void {
    // Verify we're starting with v1 schema
    expect(Schema::hasColumn(table: 'approvals', column: 'custom_state'))
        ->toBeFalse(message: 'Test should start with v1 schema');

    // Mock console confirmation to return true
    $this->artisan(command: 'approval:upgrade-to-v2')
        ->expectsConfirmation(
            question: 'Have you backed up your database? This operation modifies schema.',
            answer: 'yes'
        )
        ->expectsOutput(output: '✅ Database schema successfully upgraded to v2')
        ->assertExitCode(exitCode: 0);

    // Verify schema was upgraded and data integrity maintained
    expect(Schema::hasColumn(table: 'approvals', column: 'custom_state'))
        ->toBeTrue(message: 'Column custom_state should exist after upgrade')
        ->and(DB::table(table: 'approvals')->count())
        ->toBe(expected: $this->approvalCount, message: 'All records should be preserved');

    // Verify data integrity for a specific record with multibyte characters
    $record = DB::table(table: 'approvals')->where('approvalable_id', 5)->first();
    expect($record)->not->toBeNull()
        ->and(json_decode($record->new_data))
        ->toBeObject()
        ->name->toBe(expected: 'Test with UTF-8: 你好, こんにちは, 안녕하세요');
});

test(description: 'upgrade detects already upgraded schema', closure: function (): void {
    // First run the upgrade
    $this->artisan(command: 'approval:upgrade-to-v2')
        ->expectsConfirmation(
            question: 'Have you backed up your database? This operation modifies schema.',
            answer: 'yes'
        );

    // Run again to test detection
    $this->artisan(command: 'approval:upgrade-to-v2')
        ->expectsOutput(output: '✅ Your database is already using the v2 schema.')
        ->assertExitCode(exitCode: 0);
});

test(description: 'upgrade aborts without confirmation', closure: function (): void {
    // Run upgrade but answer "no" to backup confirmation
    $this->artisan(command: 'approval:upgrade-to-v2')
        ->expectsConfirmation(
            question: 'Have you backed up your database? This operation modifies schema.',
            answer: 'no' // default
        )
        ->expectsOutput(output: 'Please backup your database before proceeding.')
        ->assertExitCode(exitCode: 1);

    // Verify schema was not changed
    expect(Schema::hasColumn(table: 'approvals', column: 'custom_state'))
        ->toBeFalse(message: 'Schema should not be modified without confirmation');
});

test(description: 'upgrade preserves approval state counts', closure: function (string $state): void {
    // Get initial count for this state
    $initialCount = DB::table(table: 'approvals')
        ->where('state', $state)
        ->count();

    // Verify there's at least one record with this state
    expect($initialCount)->toBeGreaterThan(expected: 0);

    // Run upgrade
    $this->artisan(command: 'approval:upgrade-to-v2')
        ->expectsConfirmation(
            question: 'Have you backed up your database? This operation modifies schema.',
            answer: 'yes'
        );

    // Verify count remains the same after upgrade
    expect(DB::table(table: 'approvals'))
        ->whereState($state)
        ->count()
        ->toBe(expected: $initialCount, message: "$state approvals count should be preserved");
})->with([
    'pending',
    'approved',
    'rejected',
]);
