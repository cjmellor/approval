<?php

namespace Cjmellor\Approval\Models;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    protected $guarded = [];

    protected $casts = [
        "{config('approval.approval.new_data')}" => AsArrayObject::class,
        "{config('approval.approval.original_data')}" => AsArrayObject::class,
        'state' => ApprovalStatus::class,
    ];

    public function approvalable(): MorphTo
    {
        return $this->morphTo();
    }
}
