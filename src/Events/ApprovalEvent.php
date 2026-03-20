<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Events;

use Cjmellor\Approval\Models\Approval;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

abstract class ApprovalEvent
{
    use Dispatchable;

    public function __construct(
        public Approval $approval,
        public ?Authenticatable $user,
    ) {}
}
