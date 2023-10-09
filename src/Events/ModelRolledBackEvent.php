<?php

namespace Cjmellor\Approval\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ModelRolledBackEvent
{
    use Dispatchable;

    public function __construct()
    {
        //
    }
}
