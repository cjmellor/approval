<?php

namespace Cjmellor\Approval\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Cjmellor\Approval\Approval
 */
class Approval extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'approval';
    }
}
