<?php

namespace Cjmellor\Approval\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class ModelSetPending
{
    use Dispatchable;

    public function __construct(
        public Model $approval,
        public Authenticatable|null $user,
    ) {
    }
}
