<?php

namespace Cjmellor\Approval\Models;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Scopes\ApprovalStateScope;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    protected $guarded = [];

    protected $casts = [
        'new_data' => AsArrayObject::class,
        'original_data' => AsArrayObject::class,
        'state' => ApprovalStatus::class,
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new ApprovalStateScope());
    }

    public function approvalable(): MorphTo
    {
        return $this->morphTo();
    }

    public function approveIf(bool $boolean): void
    {
        if ($boolean) {
            $this->approve();
        }
    }

    public function approveUnless(bool $boolean): void
    {
        if (! $boolean) {
            $this->approve();
        }
    }

    public function postponeIf(bool $boolean): void
    {
        if ($boolean) {
            $this->postpone();
        }
    }

    public function postponeUnless(bool $boolean): void
    {
        if (! $boolean) {
            $this->postpone();
        }
    }

    public function rejectIf(bool $boolean): void
    {
        if ($boolean) {
            $this->reject();
        }
    }

    public function rejectUnless(bool $boolean): void
    {
        if (! $boolean) {
            $this->reject();
        }
    }
}
