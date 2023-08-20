<?php

namespace Cjmellor\Approval\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ModelApproved
{
    use Dispatchable;

    public function __construct()
    {
    }
}
