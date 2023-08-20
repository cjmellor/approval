<?php

namespace Cjmellor\Approval\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ModelRejected
{
    use Dispatchable;

    public function __construct()
    {
    }
}
