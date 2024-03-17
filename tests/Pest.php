<?php

use Approval\Approval\Enums\ApprovalStatus;
use Approval\Approval\Tests\Models\Comment;
use Approval\Approval\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(hook: function (): void {
        $this->approvalData = [
            'approvalable_type' => Comment::class,
            'approvalable_id' => 1,
            'state' => ApprovalStatus::Pending,
            'new_data' => json_encode(['comment' => 'Hello']),
            'original_data' => json_encode(['comment' => 'Goodbye']),
        ];

        $this->fakeModelData = [
            'comment' => 'I have a radio in my car',
        ];
    })
    ->in(__DIR__);
